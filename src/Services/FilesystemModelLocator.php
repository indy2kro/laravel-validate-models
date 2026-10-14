<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
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

        foreach (array_values($paths) as $i => $path) {
            $namespace = rtrim($namespaces[$i] ?? 'App\\Models', '\\');

            if (!File::isDirectory($path)) {
                continue;
            }

            $files = File::allFiles($path);
            usort($files, static fn ($a, $b): int => strcmp($a->getPathname(), $b->getPathname()));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('/', '\\', $file->getRelativePath());
                $class    = $namespace . '\\' . ($relative !== '' ? $relative . '\\' : '') . $file->getFilenameWithoutExtension();

                if (!class_exists($class)) {
                    require_once $file->getRealPath();
                }

                // Files that do not declare the expected class (traits, interfaces, enums, helpers) are not models.
                if (!class_exists($class, false)) {
                    continue;
                }

                $ref = new ReflectionClass($class);
                if ($ref->isSubclassOf(Model::class) && !$ref->isAbstract()) {
                    yield $ref->getName();
                }
            }
        }
    }
}
