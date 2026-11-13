<?php

namespace TheCorps\LaravelCqrs\Tests\Feature;

use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\Test;
use TheCorps\LaravelCqrs\Bus\QueryBus;
use TheCorps\LaravelCqrs\Bus\CommandBus;
use TheCorps\LaravelCqrs\Tests\TestCase;

final class GeneratorCommandsTest extends TestCase
{
    private string $projectPath;

    /** @var \Closure(string): void */
    private \Closure $autoloader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectPath = sys_get_temp_dir() . '/laravel-cqrs-generators-' . uniqid('', true);
        mkdir($this->projectPath . '/app', 0755, true);
        file_put_contents($this->projectPath . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ]));

        $this->app->setBasePath($this->projectPath);
        $this->app->useAppPath($this->projectPath . '/app');

        // Make the generated App\ classes autoloadable, like Composer would.
        $this->autoloader = function (string $class): void {
            if (str_starts_with($class, 'App\\')) {
                $file = $this->projectPath . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';

                if (is_file($file)) {
                    require $file;
                }
            }
        };
        spl_autoload_register($this->autoloader);
    }

    protected function tearDown(): void
    {
        spl_autoload_unregister($this->autoloader);
        $this->deleteDirectory($this->projectPath);

        parent::tearDown();
    }

    #[Test]
    public function make_cqrs_command_creates_a_working_command_handler_and_validator(): void
    {
        $this->artisan('make:cqrs-command', ['name' => 'Billing/CreateInvoice', '--validator' => true])
            ->assertExitCode(Command::SUCCESS);

        $directory = $this->projectPath . '/app/Domain/Billing/Commands/CreateInvoice';

        $this->assertFileExists("{$directory}/CreateInvoiceCommand.php");
        $this->assertFileExists("{$directory}/CreateInvoiceHandler.php");
        $this->assertFileExists("{$directory}/CreateInvoiceValidator.php");

        $command = (string) file_get_contents("{$directory}/CreateInvoiceCommand.php");

        $this->assertStringContainsString('namespace App\\Domain\\Billing\\Commands\\CreateInvoice;', $command);
        $this->assertStringContainsString('#[CommandHandler(CreateInvoiceHandler::class)]', $command);
        $this->assertStringContainsString('#[CommandValidator(CreateInvoiceValidator::class)]', $command);

        $class = 'App\\Domain\\Billing\\Commands\\CreateInvoice\\CreateInvoiceCommand';

        $this->assertNull($this->app->make(CommandBus::class)->dispatch(new $class()));
    }

    #[Test]
    public function make_cqrs_query_creates_a_working_query_and_handler(): void
    {
        $this->artisan('make:cqrs-query', ['name' => 'reporting/sales/GetMonthlyTotalsQuery'])
            ->assertExitCode(Command::SUCCESS);

        $directory = $this->projectPath . '/app/Domain/Reporting/Sales/Queries/GetMonthlyTotals';

        $this->assertFileExists("{$directory}/GetMonthlyTotalsQuery.php");
        $this->assertFileExists("{$directory}/GetMonthlyTotalsHandler.php");
        $this->assertFileDoesNotExist("{$directory}/GetMonthlyTotalsValidator.php");
        $this->assertStringNotContainsString('QueryValidator', (string) file_get_contents("{$directory}/GetMonthlyTotalsQuery.php"));

        $class = 'App\\Domain\\Reporting\\Sales\\Queries\\GetMonthlyTotals\\GetMonthlyTotalsQuery';

        $this->assertNull($this->app->make(QueryBus::class)->dispatch(new $class()));
    }

    #[Test]
    public function existing_files_are_not_overwritten_without_force(): void
    {
        $this->artisan('make:cqrs-command', ['name' => 'Billing/VoidInvoice'])->assertExitCode(Command::SUCCESS);

        $handler = $this->projectPath . '/app/Domain/Billing/Commands/VoidInvoice/VoidInvoiceHandler.php';
        file_put_contents($handler, '<?php // customised');

        $this->artisan('make:cqrs-command', ['name' => 'Billing/VoidInvoice'])
            ->expectsOutputToContain('already exists')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame('<?php // customised', file_get_contents($handler));

        $this->artisan('make:cqrs-command', ['name' => 'Billing/VoidInvoice', '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertStringContainsString('class VoidInvoiceHandler', (string) file_get_contents($handler));
    }

    #[Test]
    public function the_name_must_include_a_feature(): void
    {
        $this->artisan('make:cqrs-command', ['name' => 'CreateInvoice'])
            ->expectsOutputToContain('Feature/UseCase')
            ->assertExitCode(Command::FAILURE);

        $this->artisan('make:cqrs-command', ['name' => 'Billing/Create-Invoice'])
            ->assertExitCode(Command::FAILURE);
    }

    #[Test]
    public function published_stubs_are_used(): void
    {
        mkdir($this->projectPath . '/stubs/cqrs', 0755, true);
        file_put_contents($this->projectPath . '/stubs/cqrs/command-handler.stub', "<?php\n\n// custom stub for {{ class }}\n");

        $this->artisan('make:cqrs-command', ['name' => 'Billing/PayInvoice'])->assertExitCode(Command::SUCCESS);

        $this->assertSame(
            "<?php\n\n// custom stub for PayInvoiceHandler\n",
            file_get_contents($this->projectPath . '/app/Domain/Billing/Commands/PayInvoice/PayInvoiceHandler.php'),
        );
    }

    #[Test]
    public function cqrs_list_shows_every_message_and_its_handler(): void
    {
        $this->artisan('make:cqrs-command', ['name' => 'Shipping/ShipOrder', '--validator' => true]);
        $this->artisan('make:cqrs-query', ['name' => 'Shipping/TrackOrder']);

        $this->artisan('cqrs:list')
            ->expectsTable(['Type', 'Class', 'Handler', 'Validator'], [
                ['Command', 'App\\Domain\\Shipping\\Commands\\ShipOrder\\ShipOrderCommand', 'App\\Domain\\Shipping\\Commands\\ShipOrder\\ShipOrderHandler', 'App\\Domain\\Shipping\\Commands\\ShipOrder\\ShipOrderValidator'],
                ['Query', 'App\\Domain\\Shipping\\Queries\\TrackOrder\\TrackOrderQuery', 'App\\Domain\\Shipping\\Queries\\TrackOrder\\TrackOrderHandler', '-'],
            ])
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function cqrs_list_fails_when_a_message_cannot_be_resolved(): void
    {
        mkdir($this->projectPath . '/app/Orphans', 0755, true);
        file_put_contents($this->projectPath . '/app/Orphans/OrphanCommand.php', <<<'PHP'
            <?php

            namespace App\Orphans;

            use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;

            final class OrphanCommand implements CommandInterface
            {
                public function __construct(public readonly int $required) {}
            }
            PHP);

        $this->artisan('cqrs:list')
            ->expectsOutputToContain('No #[CommandHandler] attribute found on [App\\Orphans\\OrphanCommand]')
            ->assertExitCode(Command::FAILURE);
    }

    private function deleteDirectory(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->deleteDirectory($path . DIRECTORY_SEPARATOR . $entry);
            }
        }

        rmdir($path);
    }
}
