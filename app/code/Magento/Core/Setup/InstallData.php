<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Core\Setup;

use Magento\Framework\Module\Setup\Migration;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        /**
         * Delete rows by condition from authorization_rule
         */
        $tableName = $setup->getTable('authorization_rule');
        if ($tableName) {
            $setup->getConnection()->delete($tableName, ['resource_id = ?' => 'admin/system/tools/compiler']);
        }

        $setup->endSetup();
        
    }
}
