<?php

declare(strict_types=1);

namespace Rache\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Rache\Tests\TestCase;

/**
 * Covers the artisan commands shipped with the package.
 */
class ConsoleTest extends TestCase
{
    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->path('Rache'));
        File::delete($this->app->configPath('rache.php'));

        parent::tearDown();
    }

    #[Test]
    public function it_generates_a_rache_tag(): void
    {
        $this->artisan('make:rache-tag', ['name' => 'Search'])
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $path = $this->app->path('Rache/Tags/Search.php');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('namespace App\\Rache\\Tags;', $contents);
        $this->assertStringContainsString('class Search extends Tag', $contents);
        $this->assertStringContainsString('declare(strict_types=1);', $contents);
        $this->assertStringNotContainsString('{{', $contents);
    }

    #[Test]
    public function the_generated_tag_is_a_usable_rache_tag(): void
    {
        $this->artisan('rache:make-tag', ['name' => 'Search'])->run();

        require_once $this->app->path('Rache/Tags/Search.php');

        /** @var \Rache\Tags\RacheTagInterface $tag */
        $tag = new \App\Rache\Tags\Search(\Illuminate\Http\Request::create('/'));

        $this->assertInstanceOf(\Rache\Tags\RacheTagInterface::class, $tag);
        $this->assertSame([], $tag->getTagDetails());
    }

    #[Test]
    public function both_command_names_generate_the_same_tag(): void
    {
        $this->artisan('rache:make-tag', ['name' => 'Alpha'])->assertExitCode(Command::SUCCESS)->run();

        $this->assertFileExists($this->app->path('Rache/Tags/Alpha.php'));
    }

    #[Test]
    public function it_refuses_to_overwrite_an_existing_tag(): void
    {
        $this->artisan('make:rache-tag', ['name' => 'Search'])->run();

        File::put($this->app->path('Rache/Tags/Search.php'), '<?php // hand written');

        // Regression: this path returned false, which Laravel turns into exit
        // code 0, so a scripted `artisan make:rache-tag ... && ...` treated a
        // refused generation as a success.
        $this->artisan('make:rache-tag', ['name' => 'Search'])
            ->expectsOutputToContain('Class already exists:')
            ->assertExitCode(Command::FAILURE)
            ->run();

        $this->assertSame('<?php // hand written', File::get($this->app->path('Rache/Tags/Search.php')));
    }

    #[Test]
    public function it_overwrites_an_existing_tag_when_forced(): void
    {
        $this->artisan('make:rache-tag', ['name' => 'Search'])->run();
        File::put($this->app->path('Rache/Tags/Search.php'), '<?php // hand written');

        $this->artisan('make:rache-tag', ['name' => 'Search', '--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $this->assertStringContainsString('class Search', File::get($this->app->path('Rache/Tags/Search.php')));
    }

    #[Test]
    public function it_rejects_a_name_that_is_not_a_valid_class_name(): void
    {
        $this->artisan('make:rache-tag', ['name' => 'not a class!'])
            ->expectsOutputToContain('Invalid tag name:')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    #[Test]
    public function it_publishes_the_config(): void
    {
        $target = $this->app->configPath('rache.php');
        File::delete($target);

        $this->artisan('rache:publish')
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $this->assertFileExists($target);
        $this->assertStringContainsString("'cacheable_methods'", File::get($target));
    }

    #[Test]
    public function it_refuses_to_overwrite_a_published_config(): void
    {
        $this->artisan('rache:publish')->run();

        $this->artisan('rache:publish')
            ->expectsOutputToContain('Config file already exists')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    #[Test]
    public function it_overwrites_a_published_config_when_forced(): void
    {
        $this->artisan('rache:publish')->run();
        File::put($this->app->configPath('rache.php'), '<?php return [];');

        $this->artisan('rache:publish', ['--force' => true])
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $this->assertStringContainsString("'tags'", File::get($this->app->configPath('rache.php')));
    }

    #[Test]
    public function it_creates_the_config_directory_when_it_is_missing(): void
    {
        // Regression: Lumen ships without a config/ directory, so File::copy()
        // failed with "Failed to open stream: No such file or directory".
        $target = $this->app->configPath('rache.php');
        $directory = dirname($target);

        File::deleteDirectory($directory);
        $this->assertDirectoryDoesNotExist($directory);

        $this->artisan('rache:publish')->assertExitCode(Command::SUCCESS)->run();

        $this->assertFileExists($target);
    }
}
