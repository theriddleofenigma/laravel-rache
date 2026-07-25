<?php

declare(strict_types=1);

namespace Rache\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Shared behaviour for the commands that write files into the application.
 */
abstract class FileManipulationCommand extends Command
{
    /**
     * The filesystem helper.
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     *
     * @param  Filesystem  $files  The filesystem helper.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Get the directory generated rache tags are written to.
     */
    protected function tagDirectory(): string
    {
        return implode(DIRECTORY_SEPARATOR, [$this->applicationPath(), 'Rache', 'Tags']);
    }

    /**
     * Get the application's source directory.
     */
    protected function applicationPath(): string
    {
        if (method_exists($this->laravel, 'path')) {
            return $this->laravel->path();
        }

        return $this->laravel->basePath('app');
    }

    /**
     * Determine whether this is the first rache tag being generated.
     */
    public function isFirstTimeMakingAComponent(): bool
    {
        return ! $this->files->isDirectory($this->tagDirectory());
    }

    /**
     * Print the welcome banner shown after the first tag is generated.
     */
    public function writeWelcomeMessage(): void
    {
        $asciiLogo = <<<'EOT'
        <fg=cyan>  _____                   _             </>
        <fg=cyan> |  __ \                 | |            </>
        <fg=cyan> | |__) |   __ _    ___  | |__     ___  </>
        <fg=cyan> |  _  /   / _` |  / __| | '_ \   / _ \ </>
        <fg=cyan> | | \ \  | (_| | | (__  | | | | |  __/ </>
        <fg=cyan> |_|  \_\  \__,_|  \___| |_| |_|  \___| </>
        EOT;

        $this->line("\n".$asciiLogo."\n");
        $this->line("\n<options=bold>Congratulations, you've created your first Rache tag!</> 🎉🎉🎉\n");
        $this->line('If it saves you some time, a star on the repo is always appreciated:');
        $this->line('<fg=cyan>https://github.com/theriddleofenigma/laravel-rache</>');
    }
}
