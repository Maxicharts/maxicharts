<?php
if (! defined('ABSPATH')) {
    exit();
}

//define('PLUGIN_PATH', trailingslashit(plugin_dir_path(__FILE__)));

include_once __DIR__ . "/mcharts_utils.php";

if (! class_exists('mcharts_data_conversion_plugin')) {
    
    class mcharts_data_conversion_plugin
    {
        
        // protected static $logger = null;
        function __construct()
        {
            // MAXICHARTSAPI::getLogger()->debug("construct gfcr_custom_search_criteria");
            MAXICHARTSAPI::getLogger()->debug("Adding Module : " . __CLASS__);
            add_filter("mcharts_gf_filter_fields_after_count", array(
                $this,
                "filter_fields_after_count"
            ), 10, 2);
        }
        
        /*
         * [Pills] => Array
         * (
         * [operator] => /
         * [value] => 15
         * )
         *
         * [Injections] => Array
         * (
         * [operator] => /
         * [value] => 4
         * )
         *
         * [Implants] => Array
         * (
         * [operator] => *
         * [value] => 2.5
         * )
         */
        
        /*
         * [7] => Array
         * (
         * [Type of Device] => Pills
         * [Year] => 2015
         * [Quarter] => Q4
         * [Number] => 68
         * )
         */
        
        function transformData($scoresArray, $decoded_json_data, $args)
        {
            $decimalSeparator = $args['decimal_separator'];
            $thousandsSeparator = $args['thousands_separator'];
            // number_format ( float $number , int $decimals = 0 , string $dec_point = "." , string $thousands_sep = "," )
            
            MAXICHARTSAPI::getLogger()->debug("transform: " . implode(' / ', $decoded_json_data));
            $transformationData = $decoded_json_data['transformation'];
            MAXICHARTSAPI::getLogger()->debug($transformationData);
            $typesToChange = array_keys($transformationData);
            $roundComputedDataTo = isset($decoded_json_data['round']) ? $decoded_json_data['round'] : 2;
            MAXICHARTSAPI::getLogger()->debug("round: " . $roundComputedDataTo);
            $newScoreArray = array();
            foreach ($scoresArray as $dataKey => $dataArray) {
                MAXICHARTSAPI::getLogger()->debug("transformData : " . $dataKey . " transform: " . implode(' / ', $dataArray));
                $newScoreItemArray = array();
                foreach ($dataArray as $key => $value) {
                    MAXICHARTSAPI::getLogger()->debug("transformData : " . $value . " inside? : " . implode(' / ', $typesToChange));
                    $newScoreItemArray = array();
                    if (in_array($value, $typesToChange)) {
                        // $data_transformation
                        $operatorVal = $transformationData[$value]['operator'];
                        $operandVal = $transformationData[$value]['operand'];
                        $valueVal = $transformationData[$value]['value'];
                        $convert_to_locale = true;
                        if ($convert_to_locale) {
                            $fmt = numfmt_create(get_locale(), NumberFormatter::DECIMAL);
                            // $num = "1.234.567,891";
                            $previousVal = numfmt_parse($fmt, $dataArray[$operandVal]);
                            $valueVal = floatval($valueVal);
                        } else {
                            
                            $previousVal = floatval($dataArray[$operandVal]);
                            $valueVal = floatval($valueVal);
                        }
                        MAXICHARTSAPI::getLogger()->debug($value . " -> compute new value: " . $previousVal . ' ' . $operatorVal . " " . $valueVal);
                        $newValue = 0;
                        switch ($operatorVal) {
                            case '*':
                                $newValue = floatval($previousVal * $valueVal);
                                break;
                            case '/':
                                $newValue = floatval($previousVal / $valueVal);
                                break;
                            default:
                                break;
                        }
                        // $newValue = $value eval($dataArray['operator']) ? number_format($dataCount * 100 / $dataSum) : $dataCount;
                        
                        $newValueDisplayed = number_format($newValue, $roundComputedDataTo, $decimalSeparator, $thousandsSeparator);
                        
                        MAXICHARTSAPI::getLogger()->debug("New value = " . $newValueDisplayed);
                        $dataArray[$operandVal] = $newValue;
                    } /*
                    * else {
                    * $newScoreItemArray[$key] = $value;
                    * }
                    */
                }
                
                $newScoreArray[$dataKey] = $dataArray;
            }
            
            MAXICHARTSAPI::getLogger()->debug($newScoreArray);
            return $newScoreArray;
        }
        
        function convertScores($scoresArray, $args)
        {
            $newScoreArray = array();
            // MAXICHARTSAPI::getLogger()->debug($scoresArray);
            $dataSum = array_sum($scoresArray);
            MAXICHARTSAPI::getLogger()->debug("convertScores : " . $data_conversion . " sum: " . $dataSum);
            foreach ($scoresArray as $data => $dataCount) {
                
                $newValue = $data_conversion == "%" ? number_format($dataCount * 100 / $dataSum) : $dataCount;
                MAXICHARTSAPI::getLogger()->debug($dataCount . ' -> ' . $newValue);
                $newScoreArray[$data] = $newValue;
            }
            
            MAXICHARTSAPI::getLogger()->debug($newScoreArray);
            return $newScoreArray;
        }
        
        function filter_fields_after_count($reportFields, $args)
        {
            MAXICHARTSAPI::getLogger()->debug("### mcharts_data_conversion_plugin::filter_fields_after_count ###");
            MAXICHARTSAPI::getLogger()->debug($args);
            $data_conversion = isset($args['data_conversion']) ? $args['data_conversion'] : '';
            MAXICHARTSAPI::getLogger()->debug("### data_conversion ".$data_conversion);
            if ($data_conversion == null || empty($data_conversion)){
                return $reportFields;
            }
            MAXICHARTSAPI::getLogger()->debug($data_conversion);
            $decoded_json_data = json_decode($data_conversion, true);
            //MAXICHARTSAPI::getLogger()->debug($decoded_json_data);
            if ($decoded_json_data !== false && $decoded_json_data != null) {
                MAXICHARTSAPI::getLogger()->debug($decoded_json_data);
                
                /*foreach ($decoded_json_data as $data_name => $data_transformation) {*/
                MAXICHARTSAPI::getLogger()->debug("transform ALL result Data : " . $data_name . " transform: " . implode(' / ', $data_transformation));
                foreach ($reportFields as $key => $value) {
                    if (isset($reportFields[$key]['scores'])) {
                        $reportFields[$key]['scores'] = $this->transformData($reportFields[$key]['scores'], $decoded_json_data);
                    } else {
                        MAXICHARTSAPI::getLogger()->warn('No scores item for field ' . $key);
                    }
                }
                //}
            } else {
                MAXICHARTSAPI::getLogger()->debug("no json data");
                foreach ($reportFields as $key => $value) {
                    if (isset($reportFields[$key]['scores'])) {
                        $reportFields[$key]['scores'] = $this->convertScores($reportFields[$key]['scores'], $args);
                    } else {
                        MAXICHARTSAPI::getLogger()->warn('No scores item for field ' . $key);
                    }
                }
            }
            MAXICHARTSAPI::getLogger()->debug("filter_fields_after_count AFTER");
            MAXICHARTSAPI::getLogger()->debug($reportFields);
            return $reportFields;
        }
    }
}

new mcharts_data_conversion_plugin();