<?php

namespace Oro\Bundle\QueryDesignerBundle\Tests\Unit\Grid\DatagridConfigurationBuilder;

use Doctrine\ORM\Query;

use Oro\Bundle\QueryDesignerBundle\Tests\Unit\Fixtures\QueryDesignerModel;
use Oro\Bundle\QueryDesignerBundle\Tests\Unit\OrmQueryConverterTest;

class VirtualColumnsTest extends OrmQueryConverterTest
{
    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testVirtualColumns()
    {
        $en                    = 'Acme\Entity\TestEntity';
        $en1                   = 'Acme\Entity\TestEntity1';
        $en2                   = 'Acme\Entity\TestEntity2';
        $definition            = [
            'columns' => [
                ['name' => 'column1', 'label' => 'lbl1', 'sorting' => ''],
                ['name' => 'vc1', 'label' => 'lbl2', 'sorting' => 'DESC'],
                ['name' => 'rc1+' . $en1 . '::column2', 'label' => 'lbl3', 'sorting' => ''],
                ['name' => 'rc1+' . $en1 . '::vc2', 'label' => 'lbl4', 'sorting' => ''],
                ['name' => 'vc3', 'label' => 'lbl5', 'sorting' => ''],
            ],
            'filters' => [
                [
                    'columnName' => 'rc1+' . $en1 . '::vc2',
                    'criterion'  => [
                        'filter' => 'string',
                        'data'   => [
                            'type'  => '1',
                            'value' => 'test'
                        ]
                    ]
                ]
            ]
        ];
        $doctrine              = $this->getDoctrine(
            [
                $en  => [
                    'column1' => 'string',
                    'rc1'     => ['nullable' => true],
                ],
                $en1 => [
                    'column2' => 'integer',
                ],
                $en2 => [
                    'name' => 'string',
                ],
            ]
        );
        $virtualColumnProvider = $this->getVirtualFieldProvider(
            [
                [
                    $en,
                    'vc1',
                    [
                        'select' => [
                            'expr'        => 'emails.email',
                            'return_type' => 'string'
                        ],
                        'join'   => [
                            'left' => [
                                [
                                    'join'          => 'entity.emails',
                                    'alias'         => 'emails',
                                    'conditionType' => 'WITH',
                                    'condition'     => 'emails.primary = true'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    $en1,
                    'vc2',
                    [
                        'select' => [
                            'expr'        => 'phones.phone',
                            'return_type' => 'string'
                        ],
                        'join'   => [
                            'left' => [
                                [
                                    'join'          => 'entity.phones',
                                    'alias'         => 'phones',
                                    'conditionType' => 'WITH',
                                    'condition'     => 'phones.primary = true'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    $en,
                    'vc3',
                    [
                        'select' => [
                            'expr'        => 'COALESCE(entity.regionText, region.name)',
                            'return_type' => 'string'
                        ],
                        'join'   => [
                            'left' => [
                                [
                                    'join'  => 'entity.region',
                                    'alias' => 'region',
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );

        $model = new QueryDesignerModel();
        $model->setEntity($en);
        $model->setDefinition(json_encode($definition));
        $builder = $this->createDatagridConfigurationBuilder($model, $doctrine, null, $virtualColumnProvider);
        $result  = $builder->getConfiguration()->toArray();

        $expected = [
            'source'  => [
                'type'         => 'orm',
                'query'        => [
                    'select' => [
                        't1.column1 as c1',
                        't4.email as c2',
                        't2.column2 as c3',
                        't3.phone as c4',
                        'COALESCE(t1.regionText, t5.name) as c5',
                    ],
                    'from'   => [
                        ['table' => $en, 'alias' => 't1']
                    ],
                    'join'   => [
                        'left' => [
                            [
                                'join'  => 't1.rc1',
                                'alias' => 't2'
                            ],
                            [
                                'join'          => 't2.phones',
                                'alias'         => 't3',
                                'conditionType' => 'WITH',
                                'condition'     => 't3.primary = true'
                            ],
                            [
                                'join'          => 't1.emails',
                                'alias'         => 't4',
                                'conditionType' => 'WITH',
                                'condition'     => 't4.primary = true'
                            ],
                            [
                                'join'  => 't1.region',
                                'alias' => 't5',
                            ],
                        ]
                    ]
                ],
                'query_config' => [
                    'table_aliases'  => [
                        ''                                                                  => 't1',
                        'Acme\Entity\TestEntity::rc1'                                       => 't2',
                        'Acme\Entity\TestEntity::rc1+t2.phones|left|WITH|t3.primary = true' => 't3',
                        't1.emails|left|WITH|t4.primary = true'                             => 't4',
                        't1.region|left'                                                    => 't5',
                    ],
                    'column_aliases' => [
                        'column1'                              => 'c1',
                        'vc1'                                  => 'c2',
                        'rc1+Acme\Entity\TestEntity1::column2' => 'c3',
                        'rc1+Acme\Entity\TestEntity1::vc2'     => 'c4',
                        'vc3'                                  => 'c5',
                    ],
                    'filters'        => [
                        [
                            'column'      => 't3.phone',
                            'filter'      => 'string',
                            'filterData'  => [
                                'type'  => '1',
                                'value' => 'test'
                            ],
                            'columnAlias' => 'c4'
                        ],
                    ]
                ],
                'hints'        => [
                    [
                        'name'  => Query::HINT_CUSTOM_OUTPUT_WALKER,
                        'value' => 'Gedmo\Translatable\Query\TreeWalker\TranslationWalker',
                    ]
                ]
            ],
            'columns' => [
                'c1' => ['label' => 'lbl1', 'frontend_type' => 'string', 'translatable' => false],
                'c2' => ['label' => 'lbl2', 'frontend_type' => 'string', 'translatable' => false],
                'c3' => ['label' => 'lbl3', 'frontend_type' => 'integer', 'translatable' => false],
                'c4' => ['label' => 'lbl4', 'frontend_type' => 'string', 'translatable' => false],
                'c5' => ['label' => 'lbl5', 'frontend_type' => 'string', 'translatable' => false],
            ],
            'name'    => 'test_grid',
            'sorters' => [
                'columns' => [
                    'c1' => ['data_name' => 'c1'],
                    'c2' => ['data_name' => 'c2'],
                    'c3' => ['data_name' => 'c3'],
                    'c4' => ['data_name' => 'c4'],
                    'c5' => ['data_name' => 'c5'],
                ],
                'default' => ['c2' => 'DESC']
            ],
            'filters' => [
                'columns' => [
                    'c1' => ['data_name' => 'c1', 'type' => 'string', 'translatable' => false],
                    'c2' => ['data_name' => 'c2', 'type' => 'string', 'translatable' => false],
                    'c3' => ['data_name' => 'c3', 'type' => 'number', 'translatable' => false],
                    'c4' => ['data_name' => 'c4', 'type' => 'string', 'translatable' => false],
                    'c5' => ['data_name' => 'c5', 'type' => 'string', 'translatable' => false],
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testVirtualColumnsForEnum()
    {
        $en                    = 'Acme\Entity\TestEntity';
        $definition            = [
            'columns' => [
                ['name' => 'column1', 'label' => 'lbl1', 'sorting' => ''],
                ['name' => 'vc1', 'label' => 'lbl2', 'sorting' => 'DESC'],
            ],
            'filters' => [
                [
                    'columnName' => 'vc1',
                    'criterion'  => [
                        'filter' => 'enum',
                        'data'   => [
                            'params' => [
                                'class'      => 'Test\EnumValue',
                                'null_value' => ':empty:'
                            ],
                            'value'  => ['status1']
                        ]
                    ]
                ],
            ]
        ];
        $doctrine              = $this->getDoctrine(
            [
                $en => [
                    'column1' => 'string',
                ],
            ]
        );
        $virtualColumnProvider = $this->getVirtualFieldProvider(
            [
                [
                    $en,
                    'vc1',
                    [
                        'select' => [
                            'expr'         => 'status.name',
                            'return_type'  => 'enum',
                            'filter_by_id' => true
                        ],
                        'join'   => [
                            'left' => [
                                [
                                    'join'  => 'entity.status',
                                    'alias' => 'status'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );

        $model = new QueryDesignerModel();
        $model->setEntity($en);
        $model->setDefinition(json_encode($definition));
        $builder = $this->createDatagridConfigurationBuilder($model, $doctrine, null, $virtualColumnProvider);
        $result  = $builder->getConfiguration()->toArray();

        $expected = [
            'source'  => [
                'type'         => 'orm',
                'query'        => [
                    'select' => [
                        't1.column1 as c1',
                        't2.name as c2',
                    ],
                    'from'   => [
                        ['table' => $en, 'alias' => 't1']
                    ],
                    'join'   => [
                        'left' => [
                            [
                                'join'  => 't1.status',
                                'alias' => 't2'
                            ],
                        ]
                    ]
                ],
                'query_config' => [
                    'table_aliases'  => [
                        ''               => 't1',
                        't1.status|left' => 't2',
                    ],
                    'column_aliases' => [
                        'column1' => 'c1',
                        'vc1'     => 'c2',
                    ],
                    'filters'        => [
                        [
                            'column'      => 't1.vc1',
                            'filter'      => 'enum',
                            'filterData'  => [
                                'params' => [
                                    'class'      => 'Test\EnumValue',
                                    'null_value' => ':empty:'
                                ],
                                'value' => ['status1']
                            ],
                            'columnAlias' => 'c2'
                        ],
                    ]
                ],
                'hints'        => [
                    [
                        'name'  => Query::HINT_CUSTOM_OUTPUT_WALKER,
                        'value' => 'Gedmo\Translatable\Query\TreeWalker\TranslationWalker',
                    ]
                ]
            ],
            'columns' => [
                'c1' => ['label' => 'lbl1', 'frontend_type' => 'string', 'translatable' => false],
                'c2' => ['label' => 'lbl2', 'frontend_type' => 'enum', 'translatable' => false],
            ],
            'name'    => 'test_grid',
            'sorters' => [
                'columns' => [
                    'c1' => ['data_name' => 'c1'],
                    'c2' => ['data_name' => 'c2'],
                ],
                'default' => ['c2' => 'DESC']
            ],
            'filters' => [
                'columns' => [
                    'c1' => ['data_name' => 'c1', 'type' => 'string', 'translatable' => false],
                    'c2' => ['data_name' => 't1.vc1', 'type' => 'enum', 'translatable' => false],
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }
}
