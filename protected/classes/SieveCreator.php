<?php

class SieveCreator 
{
    public function generateSieveScript($ruleName)
    {
        $countRules = 0;
        $countActions = 0;
        foreach ($_POST as $key => $val) {
            if (preg_match('/^ruleSelect(\d+)$/', $key, $matches)) {
                if (isset($matches[1]) && $matches[1] > $countRules) {
                    $countRules = $matches[1];
                }
            }
            if (preg_match('/^actionSelect(\d+)$/', $key, $matches)) {
                if (isset($matches[1]) && $matches[1] > $countActions) {
                    $countActions = $matches[1];
                }
            }
        }

        $sieve = '';
        if ($countRules > 0) {
            $require = array();
            $conditions = array();
            $actions = array();
            for ( $i=1; $i <= $countRules; $i++ ) {
                $condition = $this->getCondition($i, $require);
                if ($condition)
                    $conditions[] = $condition;

            }
            for ( $i=1; $i <= $countActions; $i++ ) {
                $action = $this->getAction($i, $require);
                if ($action)
                    $actions[] = $action;
            }

            $sieve = $this->generateRequireHeader($require);
            $sieve.= "#rule=$ruleName\n";

            $conditions = implode(",\n", $conditions);
            $conditionsArr = array();
            foreach(explode("\n", $conditions) as $condition)
                $conditionsArr[] = empty($conditionsArr) ? $condition : $this->alignCondition($condition, $countRules);
            $conditions = implode("\n", $conditionsArr);

            $actions = implode(";\n    ", $actions);

            if ($actions && $conditions) {
                if ($countRules > 1)
                    $sieve .= "if allof($conditions) {\n    $actions;\n}\n\n";
                else
                    $sieve .= "if $conditions {\n    $actions;\n}\n\n";
            }
        }

        return $sieve;
    }

    /**
     * @param $require
     * @return string
     */
    public function generateRequireHeader($require)
    {
        $requireStr = '';
        foreach (array_keys($require) as $requireElement) {
            $requireStr[] = "\"$requireElement\"";
        }
        if (count($requireStr) > 1)
            $requireStr = '['.implode(', ', $requireStr).']';

        return $requireStr ? "require $requireStr;\n\n" : '';
    }

    /**
     * @param $actionIndex
     * @param $require
     * @return string my be empty
     */
    public function getAction($actionIndex, &$require)
    {
        $action = Parameters::getStringParameter("actionSelect{$actionIndex}");
        if ($action == 'Discard') {
            $action = 'discard';
        } else if ($action == 'Mirror to') {
            $actionText = Parameters::getStringParameter("actionText{$actionIndex}");
            if (!preg_match('#^.+?@.+?\..+?$#', $actionText))
                return '';
            $this->sieveEscape($actionText);
            $action = "redirect \"$actionText\"";
        } else if ($action == 'Mark') {
            $action = 'setflag';
            $flag = Parameters::getStringParameter("markSelect{$actionIndex}");
            if ($flag == 'Flagged')
                $action.=' "\\\\Flagged"';
            elseif ($flag == 'Read')
                $action.=' "\\\\Seen"';
            else
                return '';
            $require['imap4flags'] = true;
        } else if ($action == 'Store in') {
            $folder = Parameters::getStringParameter("foldersSelect{$actionIndex}");
            $this->sieveEscape($folder);
            $action = "fileinto \"$folder\"";
            $require['fileinto'] = true;
        } else {
            return '';
        }

        return $action;
    }

    /**
     * @param $conditionIndex
     * @param $require
     * @return string my be empty
     */
    public function getCondition($conditionIndex, &$require)
    {
        $condition = '';
        $attribute = Parameters::getStringParameter("ruleSelect{$conditionIndex}");
        $operator = Parameters::getStringParameter("opSelect{$conditionIndex}");
        if (in_array($attribute, array('From', 'Subject', 'Any To or Cc'))) {
            $text = Parameters::getStringParameter("ruleText{$conditionIndex}");
            if (empty($text))
                return '';

            $text = $this->sieveEscape($text);
            $text = "\"$text\"";
            if ($attribute == 'Any To or Cc') {
                $attribute = '["Cc", "To"]';
            } else {
                $attribute = $this->sieveEscape($attribute);
                $attribute = "\"$attribute\"";
            }

            if ($operator == 'is')
                $condition = "header :is";
            else if ($operator == 'is not')
                $condition = "not header :is";
            else if ($operator == 'in')
                $condition = "header :contains";
            else if ($operator == 'not in')
                $condition = "not header :contains";
            if (!empty($condition))
                $condition.= " $attribute $text";
        } else if ($attribute == 'Message Size') {
            $bytes = Parameters::getIntegerParameter("ruleText{$conditionIndex}");
            if (empty($bytes))
                return '';

            if ($operator == 'less than') {
                $condition = "size :under $bytes";
            } else if ($operator == 'greater than') {
                $condition = "size :over $bytes";
            } else if ($operator == 'is') {
                $bytesMinus1 = $bytes - 1;
                $bytesPlus1 = $bytes + 1;
                $condition = "allof(size :over $bytesMinus1,\n      size :under $bytesPlus1)";
            } else if ($operator == 'is not') {
                $condition = "anyof(size :under $bytes,\n      size :over $bytes)";
            }
//        } else if (in_array($attribute, array('Current Day', 'Time Of Day'))) {
//            if ($attribute == 'Current Day') {
//                $selectedWeekday = array();
//                for ($j = 1; $j <= 7; $j++) {
//                    if (Parameters::getIntegerParameter("daycheck{$j}_{$conditionIndex}")) {
//                        $dayIndex = $j == 7 ? 0 : $j;
//                        if ($operator == 'in')
//                            $selectedWeekday[] = "currentdate :is \"weekday\" \"$dayIndex\"";
//                        else if ($operator == 'not in')
//                            $selectedWeekday[] = "not currentdate :is \"weekday\" \"$dayIndex\"";
//                    }
//                }
//                if (empty($selectedWeekday))
//                    return '';
//
//                $selectedCount = count($selectedWeekday);
//                $selectedWeekday = implode(",\n      ", $selectedWeekday);
//                if ($operator == 'in' && $selectedCount > 1)
//                    $condition = "anyof($selectedWeekday)";
//                else if ($operator == 'not in' && $selectedCount > 1)
//                    $condition = "allof($selectedWeekday)";
//                else if ($selectedCount == 1)
//                    $condition = $selectedWeekday;
//            } else if ($attribute == 'Time Of Day') {
//                $from = $this->normalizeTime(Parameters::getStringParameter("ruleText1_$conditionIndex"));
//                $to = $this->normalizeTime(Parameters::getStringParameter("ruleText2_$conditionIndex"));
//
//                if ($from && $to) {
//                    $condition = "allof(currentdate \"ge\" \"time\" \"$from\",\n      currentdate \"le\" \"time\" \"$to\")";
//                }
//            }
//            $require['date'] = true;
        }

        return $condition;
    }

    public function sieveEscape($str)
    {
        return str_replace('"', '\\"', $str);
    }

    protected function normalizeTime($str)
    {
        if (preg_match('#(\d+):(\d+)#', $str, $matches)) {
            foreach ($matches as $id => $match) {
                if ($matches[$id] < 10)
                    $matches[$id] = '0'.$matches[$id];
            }
            $matches[2] = '00';
            return implode(':', $matches);
        }

        return '';
    }

    /**
     * @param $condition string
     * @param $countRules int
     * @return string
     */
    protected function alignCondition($condition, $countRules)
    {
        $conditionArr = array();
        if ($countRules > 1)
            $align = '         ';
        else
            $align = '   ';
        foreach (explode("\n", $condition) as $line) {
            $conditionArr[] = $align.$line;
        }

        return implode("\n", $conditionArr);
    }

    /**
     * @param $oldScript string
     * @param $newScript string
     * @return string
     */
    public function mergeScripts($oldScript, $newScript)
    {
        $sieve = $oldScript . $newScript;

        // combines require header
        if (preg_match_all('#^require\s\[?(.+?)\]?;$m#', $sieve, $matches)) {
            $require = array();
            foreach ($matches as $match) {
                $modules = explode(', ', $match[0]);
                $require+= $modules;
            }
            $require = array_unique($require);

            // delete old require headers
            $sieve = preg_replace('#^require.+?$\s+#ms', '', $sieve);

            $require = SieveCreator::generateRequireHeader($require);
            $sieve = $require.$sieve;
        }

        return $sieve;
    }
}
