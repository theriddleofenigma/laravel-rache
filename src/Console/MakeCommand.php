<?php

declare(strict_types=1);

namespace Rache\Console;

use Illuminate\Support\Str;

/**
 * Generates a new rache tag class in the application.
 */
class MakeCommand extends FileManipulationCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rache:make-tag {name : The name of the rache tag} {--force : Overwrite the tag if it already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Rache tag.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $class = $this->getNameInput();

        if (! $this->isValidClassName($class)) {
            $this->line("<options=bold,reverse;fg=red> BEEP-BEEP! </> 😳 \n");
            $this->line('<fg=red;options=bold>Invalid tag name:</> '.$class);
            $this->line('A rache tag name must be a valid PHP class name, for example <fg=yellow;options=bold>Search</>.');

            return self::FAILURE;
        }

        $showWelcomeMessage = $this->isFirstTimeMakingAComponent();
        $path = $this->getPath($class);

        // Bail before writing so an existing tag never has its code replaced.
        if (! $this->option('force') && $this->files->exists($path)) {
            $this->line("<options=bold,reverse;fg=red> BEEP-BEEP! </> 😳 \n");
            $this->line('<fg=red;options=bold>Class already exists:</> '.$class);
            $this->line('Re-run with <fg=yellow;options=bold>--force</> to overwrite it.');

            // See PublishCommand: a false return becomes exit code 0.
            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->buildClass($class));

        $reference = Str::snake($class);

        $this->line("<options=bold,reverse;fg=green> RACHE TAG CREATED </> 🤙 \n");
        $this->line('<options=bold;fg=yellow> Make sure to add this tag detail in the rache config. </>');
        $this->line("<options=bold><fg=cyan> REFERENCE </> '$reference' => <fg=magenta>\\{$this->getTagNamespace()}\\{$class}::class</></>");

        if ($showWelcomeMessage && ! $this->laravel->environment('testing')) {
            $this->writeWelcomeMessage();
        }

        return self::SUCCESS;
    }

    /**
     * Get the desired class name from the input.
     */
    protected function getNameInput(): string
    {
        $name = $this->argument('name');

        return trim(is_string($name) ? $name : '');
    }

    /**
     * Determine whether the given name is usable as a PHP class name.
     *
     * @param  string  $name  The raw tag name supplied on the command line.
     */
    protected function isValidClassName(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1;
    }

    /**
     * Get the absolute path the generated tag is written to.
     *
     * @param  string  $class  The tag class name.
     */
    protected function getPath(string $class): string
    {
        return $this->tagDirectory().DIRECTORY_SEPARATOR.$class.'.php';
    }

    /**
     * Get the namespace generated tags are placed in.
     *
     * Derived from the application's root namespace rather than hardcoded to
     * `App\`, so applications that renamed their namespace get a class that
     * actually autoloads.
     */
    protected function getTagNamespace(): string
    {
        return $this->rootNamespace().'Rache\\Tags';
    }

    /**
     * Get the application's root namespace, with a trailing separator.
     */
    protected function rootNamespace(): string
    {
        if (method_exists($this->laravel, 'getNamespace')) {
            return $this->laravel->getNamespace();
        }

        return 'App\\';
    }

    /**
     * Build the contents of the generated tag class.
     *
     * @param  string  $class  The tag class name.
     */
    protected function buildClass(string $class): string
    {
        $stub = $this->files->get(__DIR__.'/stubs/rache-tag.stub');

        return str_replace(
            ['{{ namespace }}', '{{namespace}}', 'DummyClass', '{{ class }}', '{{class}}'],
            [$this->getTagNamespace(), $this->getTagNamespace(), $class, $class, $class],
            $stub
        );
    }
}
