<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Tax\Model\TaxClass;

use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Tax\Api\Data\TaxClassKeyInterface;

/**
 * @codeCoverageIgnore
 */
class Key extends AbstractExtensibleModel implements TaxClassKeyInterface
{
    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->getData(TaxClassKeyInterface::KEY_TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->getData(TaxClassKeyInterface::KEY_VALUE);
    }

    /**
     * Set type of tax class key
     *
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        return $this->setData(TaxClassKeyInterface::KEY_TYPE, $type);
    }

    /**
     * Set value of tax class key
     *
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        return $this->setData(TaxClassKeyInterface::KEY_VALUE, $value);
    }
}
