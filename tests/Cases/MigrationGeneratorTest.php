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
    public function testGenerateDefault()
    {
        $generator = $this->getGenerator();

        $generator->generate('default', __DIR__, 'book');

        $code = array_shift(ContainerStub::$codes);

        $this->assertNotEmpty($code);
    }

    public function testGenerateIndex()
    {
        $generator = $this->getGenerator();

        $generator->generate('default', __DIR__, 'user_role');

        $code = array_shift(ContainerStub::$codes);

        $this->assertNotEmpty($code);
        $this->assertContains('PRIMARY KEY (`id`)', $code);
        $this->assertContains('KEY `INDEX_USER_ID` (`user_id`)', $code);
        $this->assertContains('UNIQUE KEY `INDEX_ROLE_ID` (`role_id`, `user_id`)` (`user_id`)', $code);
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
