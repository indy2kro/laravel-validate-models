<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Indy2kro\ValidateModels\Contracts\ModelLocator;
use ReflectionClass;

final class FilesystemModelLocator implements ModelLocator
{
    /**
     * @param  array<int, string>     $paths
     * @param  array<int, string>     $namespaces
     * @return iterable<class-string>
     */
    public function locate(array $paths, array $namespaces): iterable
    {
        if (\count($namespaces) === 1 && \count($paths) > 1) {
            $namespaces = array_fill(0, \count($paths), $namespaces[0]);
        }

        foreach ($paths as $i => $path) {
            $ns    = rtrim($namespaces[$i] ?? 'App\\Models', '\\');
            $files = File::exists($path) ? File::allFiles($path) : [];

            foreach ($files as $file) {
                /** @var class-string $class */
                $class = $ns . '\\' . Str::replaceLast('.php', '', $file->getFilename());

                if (!class_exists($class)) {
                    require_once $file->getRealPath();
                }

                $ref = new ReflectionClass($class);
                if ($ref->isSubclassOf(Model::class) && !$ref->isAbstract()) {
                    yield $class;
                }
            }
        }
    }
}
