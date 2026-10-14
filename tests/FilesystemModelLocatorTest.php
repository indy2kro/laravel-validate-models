<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Tests;

use Illuminate\Support\Facades\File;
use Indy2kro\ValidateModels\Services\FilesystemModelLocator;

class FilesystemModelLocatorTest extends TestCase
{
    private string $dir;

    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix          = bin2hex(random_bytes(4));
        $this->dir       = sys_get_temp_dir() . '/validate-models-' . $suffix;
        $this->namespace = 'LocatorProbe' . $suffix . '\\Models';

        File::makeDirectory($this->dir . '/Sub', 0o777, true);
        $this->write('Alpha.php', 'class Alpha extends \\Illuminate\\Database\\Eloquent\\Model {}');
        $this->write('Sub/Beta.php', 'class Beta extends \\Illuminate\\Database\\Eloquent\\Model {}', '\\Sub');
        $this->write('AbstractBase.php', 'abstract class AbstractBase extends \\Illuminate\\Database\\Eloquent\\Model {}');
        $this->write('Helper.php', 'class Helper {}');
        $this->write('Contract.php', 'interface Contract {}');
        $this->write('Mismatch.php', 'class SomethingElse extends \\Illuminate\\Database\\Eloquent\\Model {}');
        File::put($this->dir . '/notes.txt', 'not php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_finds_models_in_nested_directories_and_skips_everything_else(): void
    {
        $found = iterator_to_array((new FilesystemModelLocator())->locate([$this->dir], [$this->namespace]), false);

        $this->assertSame([
            $this->namespace . '\\Alpha',
            $this->namespace . '\\Sub\\Beta',
        ], $found);
    }

    public function test_missing_directory_yields_nothing(): void
    {
        $found = iterator_to_array((new FilesystemModelLocator())->locate([$this->dir . '/missing'], [$this->namespace]), false);

        $this->assertSame([], $found);
    }

    private function write(string $file, string $declaration, string $subNamespace = ''): void
    {
        File::put(
            $this->dir . '/' . $file,
            "<?php\n\nnamespace {$this->namespace}{$subNamespace};\n\n{$declaration}\n"
        );
    }
}
