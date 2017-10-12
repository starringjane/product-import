<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class MetaData
{
    const ENTITY_TYPE_TABLE = 'eav_entity_type';
    const PRODUCT_ENTITY_TABLE = 'catalog_product_entity';
    const ATTRIBUTE_SET_TABLE = 'eav_attribute_set';
    const ATTRIBUTE_TABLE = 'eav_attribute';
    const STORE_TABLE = 'store';

    const TYPE_DATETIME = 'datetime';
    const TYPE_DECIMAL = 'decimal';

    /** @var  Magento2DbConnection */
    private $db;

    /** @var  string  */
    public $productEntityTable;

    /** @var array Maps attribute set name to id */
    public $attributeSetMap;

    /** @var  array Maps store view code to id */
    public $storeViewMap;

    /** @var int  */
    public $productEntityTypeId;

    /** @var  EavAttributeInfo[] */
    public $eavAttributeInfo;

    public function __construct(Magento2DbConnection $db)
    {
        $this->db = $db;

        $this->productEntityTable = $db->getFullTableName(self::PRODUCT_ENTITY_TABLE);
        $this->productEntityTypeId = $this->getProductEntityTypeId();
        $this->attributeSetMap = $this->getProductAttributeSetMap();
        $this->eavAttributeInfo = $this->getEavAttributeInfo();
        $this->storeViewMap = $this->getStoreViewMap();
    }

    /**
     * Returns the id of the product entity type.
     *
     * @return int
     */
    private function getProductEntityTypeId()
    {
        $entityTypeTable = $this->db->getFullTableName(self::ENTITY_TYPE_TABLE);
        $productEntityTypeId = $this->db->fetchSingleCell("SELECT `entity_type_id` FROM {$entityTypeTable} WHERE `entity_type_code` = 'catalog_product'");
        return $productEntityTypeId;
    }

    /**
     * Returns a name => id map for product attribute sets.
     *
     * @return array
     */
    private function getProductAttributeSetMap()
    {
        $attributeSetTable = $this->db->getFullTableName(self::ATTRIBUTE_SET_TABLE);
        $map = $this->db->fetchMap("SELECT `attribute_set_name`, `attribute_set_id` FROM {$attributeSetTable} WHERE `entity_type_id` = {$this->productEntityTypeId}");
        return $map;
    }

    /**
     * Returns a code => id map for store views.
     *
     * @return array
     */
    private function getStoreViewMap()
    {
        $storeTable = $this->db->getFullTableName(self::STORE_TABLE);
        $map = $this->db->fetchMap("SELECT `code`, `store_id` FROM {$storeTable}");
        return $map;
    }

    /**
     * @return array An attribute code indexed array of AttributeInfo
     */
    private function getEavAttributeInfo()
    {
        $attributeTable = $this->db->getFullTableName(self::ATTRIBUTE_TABLE);
        $rows = $this->db->fetchAll("
            SELECT `attribute_id`, `attribute_code`, `is_required`, `backend_type` 
            FROM {$attributeTable} 
            WHERE `entity_type_id` = {$this->productEntityTypeId} AND backend_type != 'static'");

        $info = [];
        foreach ($rows as $row) {
            $info[$row['attribute_code']] = new EavAttributeInfo(
                $row['attribute_code'],
                (int)$row['attribute_id'],
                (bool)$row['is_required'],
                $row['backend_type'],
                $this->productEntityTable . '_' . $row['backend_type']);
        }
        return $info;
    }
}