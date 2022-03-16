<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Utils;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
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

        $len = count($nodes);
        foreach ($nodes as $key => $node) {
            $comments = $node->getComments();
            if ($comments) {
                $commentText = $this->pComments($comments);
                $result .= $commentText ? $this->nl . $commentText : '';

                if ($node instanceof Nop) {
                    continue;
                }
            }

            // Line breaks are required when commenting between Declare and Namespace.
            if (
                $comments &&
                ($node instanceof Declare_ || $node instanceof Namespace_)
            ) {
                $result .= PHP_EOL;
            }

            $result .= $this->nl . $this->p($node);

            $classInsideNode = (
                $node instanceof ClassConst ||
                $node instanceof Property ||
                $node instanceof ClassMethod
            );

            // Add a newline after the matching type.
            if (
                $node instanceof Declare_ ||
                $node instanceof Class_ ||
                $classInsideNode
            ) {
                $result .= PHP_EOL;
            }

            // A line break is required between use and class.
            if (
                $node instanceof Use_ &&
                isset($nodes[$key + 1]) &&
                $nodes[$key + 1] instanceof Class_
            ) {
                $result .= PHP_EOL;
            }

            // Remove the newline character of the last node inside the class.
            $isEndLastNode = $key == $len - 1;
            if ($classInsideNode && $isEndLastNode) {
                $result = rtrim($result);
            }
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
