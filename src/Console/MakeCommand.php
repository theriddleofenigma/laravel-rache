<?php

namespace Rache\Console;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeCommand extends FileManipulationCommand
{
    protected $signature = 'rache:make-tag {name} {--force}';

    protected $description = 'Create a new Rache tag.';

    public function handle()
    {
        $showWelcomeMessage = $this->isFirstTimeMakingAComponent();

        $class = $this->createClass();
        $name = Str::snake($this->getNameInput());

        if ($class) {
            $this->line("<options=bold,reverse;fg=green> RACHE TAG CREATED </> 🤙 \n");
            $this->line("<options=bold;fg=yellow> Make sure to add this tag detail in the rache config. </>");
            $this->line("<options=bold><fg=cyan> REFERENCE </> '$name' => <fg=magenta>{$this->getTagNamespace()}{$this->getNameInput()}::class</></>");

            if ($showWelcomeMessage && !app()->environment('testing')) {
                $this->writeWelcomeMessage();
            }
        }
    }

    protected function createClass()
    {
        $class = $this->getNameInput();
        $filepath = $this->getPath($this->getTagNamespace() . $class);


        // Next, We will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if (!$this->option('force') && File::exists($filepath)) {
            $this->line("<options=bold,reverse;fg=red> BEEP-BEEP! </> 😳 \n");
            $this->line('<fg=red;options=bold>Class already exists:</> ' . $class);
            return false;
        }

        $racheTagFolder = implode(DIRECTORY_SEPARATOR, [$this->laravel['path'], 'Rache', 'Tags']);
        $this->makeDirectory($racheTagFolder);

        $content = str_replace(['DummyClass', '{{ class }}', '{{class}}'], $class,
            File::get(__DIR__ . '/stubs/rache-tag.stub'));
        File::put($filepath, $content);

        return $filepath;
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param string $path
     * @return string
     */
    protected function makeDirectory(string $path): string
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        return $path;
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput(): string
    {
        return trim($this->argument('name'));
    }

    /**
     * Get the destination class path.
     *
     * @param string $name
     * @return string
     */
    protected function getPath(string $name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace(): string
    {
        return $this->laravel->getNamespace();
    }

    /**
     * @return string
     */
    protected function getTagNamespace(): string
    {
        return '\\App\\Rache\\Tags\\';
    }
}
