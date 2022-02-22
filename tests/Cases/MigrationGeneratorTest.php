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
namespace HyperfTest\Cases;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\MigrationGenerator\MigrationGenerator;
use HyperfTest\ContainerStub;

/**
 * @internal
 * @coversNothing
 */
class MigrationGeneratorTest extends AbstractTestCase
{
    public function testGenerateInteger()
    {
        $generator = $this->getGenerator();

        $generator->generate('default', __DIR__, 'book');

        $code = array_shift(ContainerStub::$codes);

        $this->assertNotEmpty($code);
    }

    protected function getGenerator(): MigrationGenerator
    {
        $container = ContainerStub::getContainer();

        return new MigrationGenerator(
            $container->get(ConnectionResolverInterface::class),
            $container->get(ConfigInterface::class)
        );
    }
}
