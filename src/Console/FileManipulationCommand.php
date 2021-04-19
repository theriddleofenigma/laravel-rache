<?php

namespace Rache\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FileManipulationCommand extends Command
{
    protected $parser;

    protected function ensureDirectoryExists($path)
    {
        if (!File::isDirectory(dirname($path))) {
            File::makeDirectory(dirname($path), 0777, $recursive = true, $force = true);
        }
    }

    public function isFirstTimeMakingAComponent()
    {
        $racheTagFolder = implode(DIRECTORY_SEPARATOR, [base_path(), 'app', 'Rache', 'Tags']);

        return !File::isDirectory($racheTagFolder);
    }

    public function writeWelcomeMessage()
    {
        $asciiLogo = <<<EOT
<fg=cyan>  _____                   _             </>
<fg=cyan> |  __ \                 | |            </>
<fg=cyan> | |__) |   __ _    ___  | |__     ___  </>
<fg=cyan> |  _  /   / _` |  / __| | '_ \   / _ \ </>
<fg=cyan> | | \ \  | (_| | | (__  | | | | |  __/ </>
<fg=cyan> |_|  \_\  \__,_|  \___| |_| |_|  \___| </>
EOT;
        //   _____                   _
        // |  __ \                 | |
        // | |__) |   __ _    ___  | |__     ___
        // |  _  /   / _` |  / __| | '_ \   / _ \
        // | | \ \  | (_| | | (__  | | | | |  __/
        // |_|  \_\  \__,_|  \___| |_| |_|  \___|
        $this->line("\n" . $asciiLogo . "\n");
        $this->line("\n<options=bold>Congratulations, you've created your first Rache tag!</> 🎉🎉🎉\n");
        if ($this->confirm('Would you like to show some love by starring the repo?')) {
            if (PHP_OS_FAMILY == 'Darwin') {
                exec('open https://github.com/theriddleofenigma/laravel-rache');
            }
            if (PHP_OS_FAMILY == 'Windows') {
                exec('start https://github.com/theriddleofenigma/laravel-rache');
            }
            if (PHP_OS_FAMILY == 'Linux') {
                exec('xdg-open https://github.com/theriddleofenigma/laravel-rache');
            }

            $this->line("Thanks! Means the world to me!");
        } else {
            $this->line("I understand, but am not going to pretend I'm not sad about it...");
        }
    }
}
