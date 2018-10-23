<?php

namespace WebSK\Entity;

use Websk\Utils\Sanitize;

class BaseWeightRepository extends BaseEntityRepository
{
    /**
     * Возвращает максимальный вес в указанном контексте (т.е. для набора пар поле - значение)
     * @param array $extra_fields_arr
     * @return int
     * @throws \Exception
     */
    public function getMaxWeightForContext($extra_fields_arr = []): int
    {
        $db_table_name = $this->getTableName();

        $where_arr = [];
        $params_arr = [];

        if (!empty($extra_fields_arr)) {
            foreach ($extra_fields_arr as $extra_field_name => $extra_field_value) {
                $extra_field_name = preg_replace('|[^a-zA-Z0-9_]|', '', $extra_field_name);

                if (is_null($extra_field_value)) {
                    $where_arr[] = $extra_field_name . ' is null';
                } else {
                    $where_arr[] = $extra_field_name . '=?';
                    $params_arr[] = $extra_field_value;
                }
            }
        }

        $sql = 'SELECT MAX(weight) FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name);
        if (count($where_arr)) {
            $sql .= ' WHERE ' . implode(' AND ', $where_arr);
        }

        $weight = $this->db_service->readField(
            $sql,
            $params_arr
        );

        return intval($weight);
    }

    /**
     * Находит в указанном контексте (т.е. для набора пар поле - значение) объект с максимальным весом,
     * меньшим чем у текущего, и меняет текущий объект с ним весами
     * т.е. объект поднимается на одну позицию вверх если сортировать по возрастанию веса
     * @param $current_item_weight
     * @param array $extra_fields_arr
     * @return false|mixed
     * @throws \Exception
     */
    public function getObjectToSwapWeightsId($current_item_weight, $extra_fields_arr = [])
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $where_arr = ['weight < ?'];
        $params_arr = [$current_item_weight];

        if (!empty($extra_fields_arr)) {
            foreach ($extra_fields_arr as $extra_field_name => $extra_field_value) {
                $extra_field_name = preg_replace('|[^a-zA-Z0-9_]|', '', $extra_field_name);

                if (is_null($extra_field_value)) {
                    $where_arr[] = $extra_field_name . ' is null';
                } else {
                    $where_arr[] = $extra_field_name . '=?';
                    $params_arr[] = $extra_field_value;
                }
            }
        }

        $sql = 'SELECT ' . Sanitize::sanitizeSqlColumnName($db_id_field_name)
            . ' FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' WHERE ' . implode(' AND ', $where_arr)
            . ' ORDER BY weight DESC, id DESC LIMIT 1';
        $object_to_swap_weights_id = $this->db_service->readField($sql, $params_arr);

        return $object_to_swap_weights_id;
    }
}
