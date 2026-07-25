<?php

declare(strict_types=1);

namespace Rache\Console;

/**
 * `make:` flavoured alias of the rache tag generator.
 */
class MakeRacheTagCommand extends MakeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:rache-tag {name : The name of the rache tag} {--force : Overwrite the tag if it already exists}';
}
