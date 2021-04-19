<?php

namespace Rache\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishCommand extends Command
{
    protected $signature = 'rache:publish {--force}';

    protected $description = 'Publish Rache configuration';

    public function handle()
    {
        $filepath = base_path('config/rache.php');
        if (!$this->option('force') && File::exists($filepath)) {
            $this->line("<options=bold,reverse;fg=red> BEEP-BEEP! </> 😳 \n");
            $this->line("Config file already exists in <fg=yellow;options=bold>config/rache.php</> location.");
            return false;
        }

        File::copy(__DIR__ . '/../../config/rache.php', $filepath);
        $this->info('The rache configuration has been published under config/rache.php location.');
    }
}
