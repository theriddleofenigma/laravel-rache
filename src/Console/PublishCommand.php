<?php

declare(strict_types=1);

namespace Rache\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Copies the packaged config file into the application.
 */
class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rache:publish {--force : Overwrite the config file if it already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Rache configuration';

    /**
     * Execute the console command.
     *
     * @param  Filesystem  $files  The filesystem helper.
     */
    public function handle(Filesystem $files): int
    {
        $target = $this->targetPath();

        if (! $this->option('force') && $files->exists($target)) {
            $this->line("<options=bold,reverse;fg=red> BEEP-BEEP! </> 😳 \n");
            $this->line('Config file already exists in <fg=yellow;options=bold>config/rache.php</> location.');
            $this->line('Re-run with <fg=yellow;options=bold>--force</> to overwrite it.');

            // Returning FAILURE rather than false: Laravel casts a false return
            // to exit code 0, which made `php artisan rache:publish && ...` in a
            // deploy script treat a refused publish as a success.
            return self::FAILURE;
        }

        // Lumen ships without a config/ directory, so copying straight into it
        // used to fail with "Failed to open stream: No such file or directory".
        $files->ensureDirectoryExists(dirname($target));

        $files->copy(__DIR__.'/../../config/rache.php', $target);

        $this->info('The rache configuration has been published under config/rache.php location.');

        return self::SUCCESS;
    }

    /**
     * Get the absolute path the config file is published to.
     */
    protected function targetPath(): string
    {
        if (method_exists($this->laravel, 'configPath')) {
            return $this->laravel->configPath('rache.php');
        }

        return $this->laravel->basePath('config/rache.php');
    }
}
