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
use Hyperf\Utils\CodeGen\PhpParser;
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
                $downStmt = new Node\Stmt\ClassMethod('down', [
                    'returnType' => new Node\Identifier('void'),
                    'flags' => Node\Stmt\Class_::MODIFIER_PUBLIC | Node\Stmt\Class_::MODIFIER_FINAL,
                    'stmts' => [
                        new Node\Stmt\Expression(
                            new Node\Expr\StaticCall(
                                new Node\Name('Schema'),
                                new Node\Identifier('dropIfExists'),
                                [
                                    new Node\Arg(new Node\Scalar\String_($this->table)),
                                ]
                            )
                        ),
                    ],
                ]);
                $class->stmts = [
                    $upStmt,
                    $downStmt,
                ];
            }
        }

        return $nodes;
    }

    private function createMethodCall(Column $column): Node\Expr\MethodCall
    {
        $type = match ($column->getType()) {
            'bigint' => 'bigInteger',
            'int' => 'integer',
            'tinyint' => 'tinyInteger',
            'varchar' => 'string',
            'datetime' => 'dateTime',
            'decimal' => 'decimal',
            'date' => 'date',
            'timestamp' => 'timestamp',
        };
        $autoIncrement = $column->getPosition() === 1;
        $unsigned = $column->getPosition() === 1;
        // ->comment('主键')
        return new Node\Expr\MethodCall(
            new Node\Expr\Variable('table'),
            new Node\Identifier('addColumn'),
            [
                new Node\Arg(new Node\Scalar\String_($type)),
                new Node\Arg(new Node\Scalar\String_($column->getName())),
                //                PhpParser::getInstance()->getExprFromValue([
                //                    'autoIncrement' => $autoIncrement,
                //                    'unsigned' => $unsigned,
                //                ]),
            ]
        );
    }

    private function createMethodCallFromNullable(Node\Expr $expr, Column $column)
    {
        if ($column->isNullable()) {
            return new Node\Expr\MethodCall(
                $expr,
                new Node\Identifier('nullable')
            );
        }

        return $expr;
    }

    private function createMethodCallFromDefault(Node\Expr $expr, Column $column)
    {
        if ($column->getDefault() !== null) {
            return new Node\Expr\MethodCall(
                $expr,
                new Node\Identifier('default'),
                [
                    new Node\Arg(new Node\Scalar\String_($column->getDefault())),
                ]
            );
        }

        return $expr;
    }

    private function createMethodCallFromComment(Node\Expr $expr, Column $column)
    {
        if ($column->getComment()) {
            return new Node\Expr\MethodCall(
                $expr,
                new Node\Identifier('comment'),
                [
                    new Node\Arg(new Node\Scalar\String_($column->getComment())),
                ]
            );
        }

        return $expr;
    }

    private function createStmtFromColumn(Column $column)
    {
        $expr = $this->createMethodCall($column);
        $expr = $this->createMethodCallFromNullable($expr, $column);
        $expr = $this->createMethodCallFromDefault($expr, $column);
        $expr = $this->createMethodCallFromComment($expr, $column);

        return new Node\Stmt\Expression($expr);
    }
}
