<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Contracts;

interface ModelLocator
{
    /**
     * @param  array<int, string>     $paths
     * @param  array<int, string>     $namespaces
     * @return iterable<class-string> Fully-qualified model class names
     */
    public function locate(array $paths, array $namespaces): iterable;
}
