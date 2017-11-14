<?php

class MvcDatabaseAdapter {

    public $db;
    public $defaults;

    function __construct() {
        $this->db = new MvcDatabase();
    }
    
    public function escape($value) {
        return $this->db->escape($value);
    }
    
    public function set_defaults($defaults) {
        $this->defaults = $defaults;
    }
    
    public function query($sql) {
        return $this->db->query($sql);
    }
    
    public function get_results($options_or_sql) {
        if (is_array($options_or_sql)) {
            $clauses = $this->get_sql_select_clauses($options_or_sql);
            $sql = implode(' ', $clauses);
        } else {
            $sql = $options_or_sql;
        }
        return $this->db->get_results($sql);
    }
    
    public function get_var($sql) {
        return $this->db->get_var($sql);
    }
    
    public function get_table_reference_sql($options=array()) {
        $table_reference = array_key_exists('table_reference', $options) ? $options['table_reference'] : $this->defaults['table_reference'];
        $table_alias = !isset($options['table_alias']) ? ' `'.$this->defaults['model_name'].'`' : $options['table_alias'];
        return $table_reference.$table_alias;
    }
    
    public function get_sql_select_clauses($options=array()) {
        $clauses = array(
            'select' => 'SELECT '.$this->get_select_sql($options),
            'from' => 'FROM '.$this->get_table_reference_sql($options),
            'joins' => $this->get_joins_sql($options),
            'where' => $this->get_where_sql($options),
            'group' => $this->get_group_sql($options),
            'order' => $this->get_order_sql($options),
            'limit' => $this->get_limit_sql($options),
        );
        
        return $clauses;
    }
    
    public function get_select_sql($options=array()) {
        $selects = array_key_exists('selects', $options) ? $options['selects'] : $this->defaults['selects'] ;
        if (!empty($options['additional_selects'])) {
            $selects = array_merge($this->defaults['selects'], $options['additional_selects']);
        }
        return implode(', ', $selects);
    }
    
    public function get_joins_sql($options=array()) {
        $joins = array_key_exists('joins', $options) ? $options['joins'] : $this->defaults['joins'];
        if (empty($joins)) {
            return '';
        }
        $clauses = array();
        if (isset($joins['table'])) {
            $joins = array($joins);
        }
        foreach ($joins as $join) {
            $type = empty($join['type']) ? 'JOIN' : $join['type'];
            $clauses[] = $type.' '.MvcModel::process_table_name($join['table']).' '.$join['alias'].' ON '.$join['on'];
        }
        return implode(' ', $clauses);
    }
    
    public function get_where_sql($options=array()) {
        $conditions = array_key_exists('conditions', $options) ? $options['conditions'] : $this->defaults['conditions'] ;
        if (empty($conditions)) {
            return '';
        }
        if (is_array($conditions)) {
            $sql_clauses = $this->get_where_sql_clauses($conditions, $options);
            return 'WHERE '.implode(' AND ', $sql_clauses);
        }
        return 'WHERE '.$conditions;
    }
    
    public function get_group_sql($options=array()) {
        if (empty($options['group'])) {
            return '';
        }
        return 'GROUP BY '.$options['group'];
    }
    
    public function get_where_sql_clauses($conditions, $options=array()) {
        $use_table_alias = isset($options['use_table_alias']) ? $options['use_table_alias'] : true;
        $sql_clauses = array();
        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                if (is_string($key) && !in_array($key, array('OR', 'AND'))) {
                    $values = array();
                    foreach ($value as $val) {
                        $values[] = '"'.$this->escape($val).'"';
                    }
                    $values = implode(',', $values);
                    $sql_clauses[] = $this->escape($key).' IN ('.$values.')';
                } else {
                    $clauses = $this->get_where_sql_clauses($value, $options);
                    $logical_operator = $key == 'OR' ? ' OR ' : ' AND ';
                    $sql_clauses[] = '('.implode($logical_operator, $clauses).')';
                }
                continue;
            }
            if (strpos($key, '.') === false && $use_table_alias) {
                $key = $this->defaults['model_name'].'.'.$key;
            }

            $operator = preg_match('/\s+(<|>|<=|>=|<>|\!=|IS|[\w\s]+)/', $key) ? ' ' : ' = ';
            
            $functions = array('NULL', 'NOW()', 'CURDATE()', 'CURTIME()', 'CURRENT_TIMESTAMP()', 'CURRENT_TIMESTAMP()');
            
            if(in_array($value, $functions) || is_null($value)){
                if((is_null($value) || $value === 'NULL')){
                    if(trim($operator) == "="){
                        $operator = " IS ";
                    }
                    else if(trim($operator) == "!="){
                        $operator = " IS NOT ";
                    }
                    $value = "NULL";
                }
                $sql_clauses[] = $this->escape($key).$operator.$value;
            }
            else{
                $sql_clauses[] = $this->escape($key).$operator.'"'.$this->escape($value).'"';
            }
        }
        return $sql_clauses;
    }
    
    public function get_order_sql($options=array()) {
        $order = array_key_exists('order', $options) ? $options['order'] : $this->defaults['order'];
        return $order ? 'ORDER BY '.$this->escape($order) : '';
    }
    
    public function get_limit_sql($options=array()) {
        if (!empty($options['page'])) {
            $per_page = empty($options['per_page']) ? $this->defaults['per_page'] : $options['per_page'];
            $page = $options['page'];
            $offset = ($page - 1) * $per_page;
            return 'LIMIT '.$this->escape($offset).', '.$this->escape($per_page);
        }
        $limit = array_key_exists('limit', $options) ? $options['limit'] : $this->defaults['limit'];
        return $limit ? 'LIMIT '.$this->escape($limit) : '';
    }
    
    public function get_set_sql($data) {
        $clauses = array();
        foreach ($data as $key => $value) {
            if ($value === null) {
                $clauses[] = '`' . $key . '` = NULL';
            } else if ($value === false) {
                $clauses[] = '`' . $key . '` = FALSE';
            } else if ($value === true) {
                $clauses[] = '`' . $key . '` = TRUE';
            } else if (is_string($value) || is_numeric($value)) {
                $clauses[] = '`' . $key . '` = "' . $this->escape($value) . '"';
            }
        }
        $sql = implode(', ', $clauses);
        return $sql;
    }
    
    public function get_insert_columns_sql($data) {
        $columns = array_keys($data);
        $columns = $this->db->escape_array($columns);
        $sql = '(`'.implode('`, `', $columns).'`)';
        return $sql;
    }
    
    public function get_insert_values_sql($data) {
        $values = array();
        foreach ($data as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } elseif ($value === false) {
                $values[] = 'FALSE';
            } elseif ($value === true) {
                $values[] = 'TRUE';
            } else {
                $values[] = '"'.$this->escape($value).'"';
            }
        }
        $sql = '('.implode(', ', $values).')';
        return $sql;
    }
    
    public function insert($data, $options=array()) {
        $options['table_alias'] = false;
        $options['use_table_alias'] = false;
        if (empty($options['table_reference'])) {
            // Filter out any data with a key that doesn't correspond to a column name in the table
            $data = array_intersect_key($data, $this->schema);
        }
        $clauses = array(
            'insert' => 'INSERT INTO '.$this->get_table_reference_sql($options),
            'insert_columns' => $this->get_insert_columns_sql($data),
            'insert_values' => 'VALUES '.$this->get_insert_values_sql($data)
        );
        $sql = implode(' ', $clauses);
        $this->query($sql);
        $insert_id = $this->db->insert_id();
        return $insert_id;
    }
    
    public function update_all($data, $options=array()) {
        $clauses = array(
            'update' => 'UPDATE '.$this->get_table_reference_sql($options),
            'set' => 'SET '.$this->get_set_sql($data),
            'where' => $this->get_where_sql($options),
            'limit' => $this->get_limit_sql($options)
        );
        $sql = implode(' ', $clauses);
        $this->query($sql);
    }
    
    public function delete_all($options) {
        $options['table_alias'] = false;
        $options['use_table_alias'] = false;
        $clauses = array(
            'update' => 'DELETE FROM '.$this->get_table_reference_sql($options),
            'where' => $this->get_where_sql($options),
            'limit' => $this->get_limit_sql($options)
        );
        $sql = implode(' ', $clauses);
        $this->query($sql);
    }

}

?>
