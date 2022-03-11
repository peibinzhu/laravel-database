<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace PeibinLaravel\Database\Commands\Ast;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PeibinLaravel\Database\Commands\ModelOption;
use PeibinLaravel\Utils\CodeGen\PhpDocReader;
use PeibinLaravel\Utils\CodeGen\PhpParser;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use RuntimeException;

class ModelUpdateVisitor extends NodeVisitorAbstract
{
    public const RELATION_METHODS = [
        'hasMany'        => HasMany::class,
        'hasManyThrough' => HasManyThrough::class,
        'hasOneThrough'  => HasOneThrough::class,
        'belongsToMany'  => BelongsToMany::class,
        'hasOne'         => HasOne::class,
        'belongsTo'      => BelongsTo::class,
        'morphOne'       => MorphOne::class,
        'morphTo'        => MorphTo::class,
        'morphMany'      => MorphMany::class,
        'morphToMany'    => MorphToMany::class,
        'morphedByMany'  => MorphToMany::class,
    ];

    /**
     * @var Model
     */
    protected $class;

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @var ModelOption
     */
    protected $option;

    /**
     * @var Node\Stmt\ClassMethod[]
     */
    protected $methods = [];

    /**
     * @var array
     */
    protected $properties = [];

    public function __construct($class, $columns, ModelOption $option)
    {
        $this->class = new $class();
        $this->columns = $columns;
        $this->option = $option;
    }

    public function beforeTraverse(array $nodes)
    {
        $this->methods = PhpParser::getInstance()->getAllMethodsFromStmts($nodes);
        sort($this->methods);

        $this->initPropertiesFromMethods();

        return null;
    }

    public function leaveNode(Node $node)
    {
        switch ($node) {
            case $node instanceof Node\Stmt\Class_:
                $node->setDocComment(new Doc($this->parse($node)));
                return $node;
        }
        return null;
    }

    protected function parse(Node\Stmt\Class_ $node): string
    {
        $doc = '/**' . PHP_EOL;
        $doc = $this->parseProperty($doc);
        $doc = $this->parseMethod($doc, $node->getDocComment()->getText());
        $doc .= ' */';
        return $doc;
    }

    protected function parseProperty(string $doc): string
    {
        foreach ($this->columns as $column) {
            [$name, $type, $comment] = $this->getProperty($column);
            if (array_key_exists($name, $this->properties)) {
                if (!empty($comment)) {
                    $this->properties[$name]['comment'] = $comment;
                }
                continue;
            }
            $doc .= sprintf(' * @property %s $%s %s', $type, $name, $comment) . PHP_EOL;
        }
        foreach ($this->properties as $name => $property) {
            $comment = $property['comment'] ?? '';
            if ($property['read'] && $property['write']) {
                $doc .= sprintf(' * @property %s $%s %s', $property['type'], $name, $comment) . PHP_EOL;
                continue;
            }
            if ($property['read']) {
                $doc .= sprintf(' * @property-read %s $%s %s', $property['type'], $name, $comment) . PHP_EOL;
                continue;
            }
            if ($property['write']) {
                $doc .= sprintf(' * @property-write %s $%s %s', $property['type'], $name, $comment) . PHP_EOL;
                continue;
            }
        }
        return $doc;
    }

    protected function parseMethod(string $doc, string $comment): string
    {
        foreach (explode(PHP_EOL, $comment) as $line) {
            if (strpos($line, '@method')) {
                $doc .= $line . PHP_EOL;
            }
        }
        return $doc;
    }

    protected function initPropertiesFromMethods()
    {
        $reflection = new \ReflectionClass(get_class($this->class));
        $casts = $this->class->getCasts();

        foreach ($this->methods as $methodStmt) {
            $methodName = $methodStmt->name->name;
            $method = $reflection->getMethod($methodName);
            if (Str::startsWith($method->getName(), 'get') && Str::endsWith($method->getName(), 'Attribute')) {
                // Magic get<name>Attribute
                $name = Str::snake(substr($method->getName(), 3, -9));
                if (!empty($name)) {
                    $type = PhpDocReader::getInstance()->getReturnType($method, true);
                    $this->setProperty($name, $type, true, null, '', false, 1);
                }
                continue;
            }

            if (Str::startsWith($method->getName(), 'set') && Str::endsWith($method->getName(), 'Attribute')) {
                // Magic set<name>Attribute
                $name = Str::snake(substr($method->getName(), 3, -9));
                if (!empty($name)) {
                    $this->setProperty($name, null, null, true, '', false, 1);
                }
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            $return = end($methodStmt->stmts);
            if ($return instanceof Node\Stmt\Return_) {
                $expr = $return->expr;
                if (
                    $expr instanceof Node\Expr\MethodCall
                    && $expr->name instanceof Node\Identifier
                    && is_string($expr->name->name)
                ) {
                    $loop = 0;
                    while ($expr->var instanceof Node\Expr\MethodCall) {
                        if ($loop > 32) {
                            throw new RuntimeException('max loop reached!');
                        }
                        ++$loop;
                        $expr = $expr->var;
                    }
                    $name = $expr->name->name;
                    if (array_key_exists($name, self::RELATION_METHODS)) {
                        if ($name === 'morphTo') {
                            // Model isn't specified because relation is polymorphic
                            $this->setProperty($method->getName(), ['\\' . Model::class], true);
                        } elseif (
                            isset($expr->args[0]) &&
                            $expr->args[0]->value instanceof Node\Expr\ClassConstFetch
                        ) {
                            $related = $expr->args[0]->value->class->toCodeString();
                            if (strpos($name, 'Many') !== false) {
                                // Collection or array of models (because Collection is Arrayable)
                                $this->setProperty(
                                    $method->getName(),
                                    [$this->getCollectionClass($related), $related . '[]'],
                                    true
                                );
                            } else {
                                // Single model is returned
                                $this->setProperty($method->getName(), [$related], true);
                            }
                        }
                    }
                }
            }
        }

        // The custom caster.
        foreach ($casts as $key => $caster) {
            if (is_subclass_of($caster, Castable::class)) {
                $caster = $caster::castUsing([]);
            }

            if (is_subclass_of($caster, CastsAttributes::class)) {
                $ref = new \ReflectionClass($caster);
                $method = $ref->getMethod('get');
                if ($type = $method->getReturnType()) {
                    // Get return type which defined in `CastsAttributes::get()`.
                    $this->setProperty($key, ['\\' . ltrim($type->getName(), '\\')], true, true);
                }
            }
        }
    }

    protected function setProperty(
        string $name,
        array $type = null,
        bool $read = null,
        bool $write = null,
        string $comment = '',
        bool $nullable = false,
        int $priority = 0
    ) {
        if (!isset($this->properties[$name])) {
            $this->properties[$name] = [];
            $this->properties[$name]['type'] = 'mixed';
            $this->properties[$name]['read'] = false;
            $this->properties[$name]['write'] = false;
            $this->properties[$name]['comment'] = (string)$comment;
            $this->properties[$name]['priority'] = 0;
        }
        if ($this->properties[$name]['priority'] > $priority) {
            return;
        }
        if ($type !== null) {
            if ($nullable) {
                $type[] = 'null';
            }
            $this->properties[$name]['type'] = implode('|', array_unique($type));
        }
        if ($read !== null) {
            $this->properties[$name]['read'] = $read;
        }
        if ($write !== null) {
            $this->properties[$name]['write'] = $write;
        }
        $this->properties[$name]['priority'] = $priority;
    }

    protected function getProperty($column): array
    {
        $name = $column['column_name'];

        $type = $this->formatPropertyType($column['data_type'], $column['cast'] ?? null);

        $comment = $this->option->isWithComments() ? $column['column_comment'] ?? '' : '';
        $comment = str_replace(["\r\n", "\r", "\n"], '', $comment);

        return [$name, $type, $comment];
    }

    protected function formatDatabaseType(string $type): ?string
    {
        switch ($type) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return 'integer';
            case 'bool':
            case 'boolean':
                return 'boolean';
            default:
                return null;
        }
    }

    protected function formatPropertyType(string $type, ?string $cast): ?string
    {
        if (!isset($cast)) {
            $cast = $this->formatDatabaseType($type) ?? 'string';
        }

        switch ($cast) {
            case 'integer':
                return 'int';
            case 'date':
            case 'datetime':
                return '\Carbon\Carbon';
            case 'json':
                return 'array';
        }

        return $cast;
    }

    protected function getCollectionClass($className): string
    {
        // Return something in the very unlikely scenario the model doesn't
        // have a newCollection() method.
        if (!method_exists($className, 'newCollection')) {
            return '\\' . Collection::class;
        }

        /** @var Model $model */
        $model = new $className();
        return '\\' . get_class($model->newCollection());
    }
}
