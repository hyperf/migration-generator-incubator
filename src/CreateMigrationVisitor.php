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
namespace Hyperf\MigrationGenerator;

use Hyperf\Database\Commands\ModelOption;
use Hyperf\Database\Schema\Column;
use Hyperf\Utils\Collection;
use Hyperf\Utils\Str;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class CreateMigrationVisitor extends NodeVisitorAbstract
{
    /**
     * @param Collection<int, Column> $columns
     */
    public function __construct(private string $table, private ModelOption $option, private Collection $columns)
    {
    }

    public function afterTraverse(array $nodes)
    {
        foreach ($nodes as $class) {
            if ($class instanceof Node\Stmt\Class_) {
                $class->name = Str::studly('create_' . Str::snake($this->table) . '_table');
                $upStmt = new Node\Stmt\ClassMethod('up', [
                    'returnType' => new Node\Identifier('void'),
                    'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC | Node\Stmt\Class_::MODIFIER_FINAL,
                    'stmts' => [
                        new Node\Stmt\Expression(
                            new Node\Expr\StaticCall(
                                new Node\Name('Schema'),
                                new Node\Identifier('create'),
                                [
                                    new Node\Arg(new Node\Scalar\String_($this->table)),
                                    new Node\Arg(new Node\Expr\Closure([
                                        'params' => [
                                            new Node\Param(
                                                new Node\Expr\Variable('table'),
                                                null,
                                                new Node\Name('Blueprint')
                                            ),
                                        ],
                                        'stmts' => value(function () {
                                            $result = [];
                                            foreach ($this->columns as $column) {
                                                $result[] = $this->createStmtFromColumn($column);
                                            }
                                            return $result;
                                        }),
                                    ])),
                                ]
                            )
                        ),
                    ],
                ]);
                $class->stmts = [
                    $upStmt,
                ];
            }
        }

        return $nodes;
    }

    private function createStmtFromColumn(Column $column)
    {
        var_dump($column->getPosition());
        return new Node\Stmt\Expression(
            new Node\Expr\MethodCall(
                new Node\Expr\Variable('table'),
                new Node\Identifier('bigIncrements'),
                [
                    new Node\Arg(new Node\Scalar\String_('id')),
                ]
            )
        );
    }
}
