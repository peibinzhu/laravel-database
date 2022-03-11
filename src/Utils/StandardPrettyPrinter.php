<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Utils;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\PrettyPrinter\Standard;

class StandardPrettyPrinter extends Standard
{
    /**
     * Pretty prints an array of nodes (statements) and indents them optionally.
     *
     * @param Node[] $nodes  Array of nodes
     * @param bool   $indent Whether to indent the printed nodes
     *
     * @return string Pretty printed statements
     */
    protected function pStmts(array $nodes, bool $indent = true): string
    {
        if ($indent) {
            $this->indent();
        }

        $result = '';

        $lastClass = null;
        foreach ($nodes as $node) {
            $comments = $node->getComments();
            if ($comments) {
                $result .= $this->nl . $this->pComments($comments);
                if ($node instanceof Nop) {
                    continue;
                }
            }

            $result .= $this->nl . $this->p($node);

            // Add a newline after the matching type.
            if (
                $node instanceof Declare_ ||
                $node instanceof Property ||
                $node instanceof Class_ ||
                $node instanceof ClassMethod ||
                get_class($node) == $lastClass
            ) {
                $result .= PHP_EOL;
            }

            // Remove newline from last method.
            if ($node instanceof ClassMethod && get_class($node) == $lastClass) {
                $result = rtrim($result);
            }

            $lastClass = get_class($node);
        }

        if ($indent) {
            $this->outdent();
        }
        return $result;
    }

    protected function pStmt_Declare(Declare_ $node): string
    {
        return 'declare(' . $this->pCommaSeparated($node->declares) . ')'
            . (null !== $node->stmts ? ' {' . $this->pStmts($node->stmts) . $this->nl . '}' : ';');
    }
}
