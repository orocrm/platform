<?php

namespace Oro\Bundle\GridBundle\Tests\Unit\Filter\ORM;

use Oro\Bundle\FilterBundle\Form\Type\Filter\DateRangeFilterType;
use Oro\Bundle\GridBundle\Filter\ORM\DateRangeFilter;

class DateRangeFilterTest extends AbstractDateFilterTest
{
    /**
     * @return DateRangeFilter
     */
    protected function createTestFilter()
    {
        return new DateRangeFilter($this->getTranslatorMock());
    }

    public function testGetDefaultOptions()
    {
        $this->assertEquals(array('form_type' => DateRangeFilterType::NAME), $this->model->getDefaultOptions());
    }
}
