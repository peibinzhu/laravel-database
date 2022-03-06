<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Utils;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
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
        foreach ($nodes as $node) {
            $comments = $node->getComments();
            if ($comments) {
                $result .= $this->nl . $this->pComments($comments);
                if ($node instanceof Nop) {
                    continue;
                }
            }

            $result .= $this->nl . $this->p($node);

            if (
                $node instanceof Declare_ ||
                $node instanceof Use_ ||
                $node instanceof Property ||
                $node instanceof ClassMethod
            ) {
                $result .= $this->nl;
            }
        }

        if ($indent) {
            $this->outdent();
        }

        return $result;
    }
}
