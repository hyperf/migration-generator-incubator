<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\MigrationGenerator\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Commands\ModelOption;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Schema\Builder;
use Hyperf\Database\Schema\Column;
use Hyperf\DbConnection\Db;
use Hyperf\MigrationGenerator\CreateMigrationVisitor;
use Hyperf\Utils\Collection;
use Hyperf\Utils\Filesystem\Filesystem;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class GenerateMigrationCommand extends HyperfCommand
{
    protected ?ConnectionResolverInterface $resolver = null;

    protected ?ConfigInterface $config = null;

    protected ?Parser $astParser = null;

    protected ?PrettyPrinterAbstract $printer = null;

    protected ?Filesystem $files = null;

    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('gen:migration-from-database');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Generate migrations from an existing table structure');
        $this->addArgument('table', InputArgument::OPTIONAL, 'Which table you want generated.');
        $this->addOption('pool', 'p', InputOption::VALUE_OPTIONAL, 'The connection pool you want the migration to be generated.', 'default');
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path that you want the migration to be generated.', 'migrations');
    }

    public function handle()
    {
        $table = $this->input->getArgument('table');
        $pool = $this->input->getOption('pool');
        $path = $this->input->getOption('path');

        $this->resolver = $this->container->get(ConnectionResolverInterface::class);
        $this->config = $this->container->get(ConfigInterface::class);
        $this->astParser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard();
        $this->files = make(Filesystem::class);

        $option = tap(new ModelOption(), static function (ModelOption $option) use ($pool, $path) {
            $option->setPool($pool);
            $option->setPath($path);
        });

        try {
            if ($table) {
                $this->createMigration($table, $option);
            } else {
                $this->createMigrations($option);
            }
        } catch (\Throwable $e) {
            $this->error("<error>[ERROR] Created Migration:</error> {$e->getMessage()}");
        }
    }

    public function getColumnArray(ModelOption $option, ?string $table = null):array
    {
        $query = Db::select('select * from information_schema.columns where `table_name` = ? order by ORDINAL_POSITION', [ $table ]);
        foreach ( $query as $item ) {
            return (array) $item;
        }
    }

    public function getColumns(ModelOption $option, ?string $table = null): Collection
    {
        $pool = $option->getPool();
        $columns = Context::getOrSet('database.columns.' . $pool, function () use ($pool) {
            $builder = $this->getSchemaBuilder($pool);
            return $builder->getColumns();
        });

        if ($table) {
            return collect($columns)->filter(static function (Column $column) use ($table) {
                return $column->getTable() === $table;
            })->sort(static function (Column $a, Column $b) {
                return $a->getPosition() - $b->getPosition();
            });
        }

        return collect($columns);
    }

    public function createMigration(string $table, ModelOption $option)
    {
        if (!defined('BASE_PATH')) {
            throw new \InvalidArgumentException('Please set constant `BASE_PATH`.');
        }

        $stub = __DIR__ . '/../../stubs/create_from_database.stub.php';
        if (!file_exists($stub)) {
            $stub = BASE_PATH . '/vendor/migration-generator-incubator/stubs/create_from_database.stub.php';
            if (!file_exists($stub)) {
                throw new \InvalidArgumentException('create_from_database.stub does not exists.');
            }
        }

        $columns = $this->getColumns($option, $table);
        $columnArray = $this->getColumnArray($option, $table);
        $code = file_get_contents($stub);
        $stmts = $this->astParser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CreateMigrationVisitor($table, $option, $columns, $columnArray));
        $stmts = $traverser->traverse($stmts);
        $code = $this->printer->prettyPrintFile($stmts);

        $path = BASE_PATH . '/' . $option->getPath();
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $this->files->put(
            $path = $this->getPath('create_' . $table . '_table', $path),
            $code
        );

        $file = pathinfo($path, PATHINFO_FILENAME);

        $this->info("<info>[INFO] Created Migration:</info> {$file}");
    }

    public function createMigrations(ModelOption $option)
    {
        $builder = $this->getSchemaBuilder($option->getPool());
        $tables = [];

        foreach ($builder->getAllTables() as $row) {
            $row = (array)$row;
            $table = reset($row);
            if (!$this->isIgnoreTable($table, $option)) {
                $tables[] = $table;
            }
        }

        foreach ($tables as $table) {
            $this->createMigration($table, $option);
        }
    }

    protected function isIgnoreTable(string $table, ModelOption $option): bool
    {
        if (in_array($table, $option->getIgnoreTables())) {
            return true;
        }

        return $table === $this->config->get('databases.migrations', 'migrations');
    }

    protected function getSchemaBuilder(string $poolName): Builder
    {
        $connection = $this->resolver->connection($poolName);
        return $connection->getSchemaBuilder();
    }

    /**
     * Get the full path to the migration.
     */
    protected function getPath(string $name, string $path): string
    {
        return $path . '/' . $this->getDatePrefix() . '_' . $name . '.php';
    }

    /**
     * Get the date prefix for the migration.
     */
    protected function getDatePrefix(): string
    {
        return date('Y_m_d_His');
    }
}
