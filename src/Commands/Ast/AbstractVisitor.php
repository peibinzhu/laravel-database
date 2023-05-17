<?php

declare(strict_types=1);

namespace PeibinLaravel\Database\Commands\Ast;

use PeibinLaravel\Database\Commands\ModelData;
use PeibinLaravel\Database\Commands\ModelOption;
use PhpParser\NodeVisitorAbstract;

abstract class AbstractVisitor extends NodeVisitorAbstract
{
    public function __construct(protected ModelOption $option, protected ModelData $data)
    {
    }
}
