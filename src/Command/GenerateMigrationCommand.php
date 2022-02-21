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
use Psr\Container\ContainerInterface;

class GenerateMigrationCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('gen:migration-from-database');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Generate migrations from an existing table structure');
    }

    public function handle()
    {
        $this->line('Hello Hyperf!', 'info');
    }
}
