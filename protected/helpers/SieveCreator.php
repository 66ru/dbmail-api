<?php

class SieveCreator
{
    /**
     * @param string $ruleName
     * @param array $rules
     * @param array $actions
     * @return string
     */
    public static function generateSieveScript($ruleName, $rules, $actions)
    {
        $requireArr = array();

        $actions = self::getActions($actions, $requireArr);
        $conditions = self::getConditions($rules, $requireArr);
        $require = self::generateRequireHeader(array_keys($requireArr));

        $actions = implode(";\n    ", $actions);

        // align conditions
        $conditions = implode(",\n", $conditions);
        $conditionsArr = array();
        foreach (explode("\n", $conditions) as $condition)
            $conditionsArr[] = empty($conditionsArr) ? $condition : self::alignCondition($condition, count($rules));
        $conditions = implode("\n", $conditionsArr);

        if ($actions && $conditions) {
            $sieve = $require . "#rule=$ruleName\n";
            if (!empty($requireArr))
                $sieve .= '#require=' . json_encode($requireArr) . "\n";

            if (count($rules) > 1)
                $conditions = "allof($conditions)";
            $sieve .= "if $conditions {\n    $actions;\n}\n\n";
            return $sieve;
        } else {
            return '';
        }
    }

    /**
     * @param array $actions
     * @param string[] $require
     * @return string[]
     */
    protected static function getActions($actions, &$require)
    {
        $actionsArr = array();
        foreach ($actions as $action => $attribute) {
            if ($action == 'Discard') {
                $actionsArr[] = 'discard';
            } else if ($action == 'Mirror to') {
                if (!is_string($attribute) || !preg_match('#^.+?@.+?\..+?$#', $attribute))
                    throw new CException("wrong email $attribute");
                $attribute = self::sieveEscape($attribute);
                $actionsArr[] = "redirect \"$attribute\"";
            } else if ($action == 'Mark') {
                if (!is_string($attribute) || !in_array($attribute, array('Flagged', 'Read')))
                    throw new CException("wrong attribute $attribute");

                $require['imap4flags'] = true;
                $action = 'keep :flags ';
                if ($attribute == 'Flagged')
                    $action .= ' "\\\\Flagged"';
                elseif ($attribute == 'Read')
                    $action .= ' "\\\\Seen"';
                $actionsArr[] = $action;
            } else if ($action == 'Store in') {
                if (empty($attribute) || !is_string($attribute))
                    throw new CException("wrong attribute");
                $attribute = self::sieveEscape($attribute);
                $actionsArr[] = "fileinto \"$attribute\"";
                $require['fileinto'] = true;
            } else {
                throw new CException("unknown action $action");
            }
        }

        return $actionsArr;
    }

    /**
     * @param array $rules
     * @param string[] $require
     * @return string[]
     */
    protected static function getConditions($rules, &$require)
    {
        $conditions = array();
        foreach ($rules as $attribute => $rule) {
            $condition = self::getCondition($attribute, $rule, $require);
            if ($condition)
                $conditions[] = $condition;
        }

        return $conditions;
    }

    /**
     * @param $rule array
     * @param $attribute string
     * @param string[] $require
     * @return string my be empty
     */
    protected static function getCondition($attribute, $rule, &$require)
    {
        if (in_array($attribute, array('From', 'Subject', 'Any To or Cc', 'X-Spam-Flag'))) {
            if (empty($rule['value']) || empty($rule['operation']) ||
                    !is_string($rule['value']) || !is_string($rule['operation']))
                throw new CException("value or operation are wrong");
            if (!in_array($rule['operation'], array('is', 'is not')))
                throw new CException("operation wrong");

            // if value surrounded by asterisks - convert operation to internal kind
            if (strpos($rule['value'], '*') === 0 && strrpos($rule['value'], '*') === strlen($rule['value']) - 1) {
                if ($rule['operation'] == 'is') {
                    $rule['operation'] = 'in';
                    $rule['value'] = trim($rule['value'], '*');
                } else if ($rule['operation'] == 'is not') {
                    $rule['operation'] = 'not in';
                    $rule['value'] = trim($rule['value'], '*');
                }
            }

            if ($attribute == 'Any To or Cc') {
                $attribute = '["Cc", "To"]';
            } else {
                $attribute = self::sieveEscape($attribute);
                $attribute = "\"$attribute\"";
            }

            $condition = '';
            if ($rule['operation'] == 'is')
                $condition = "header :is";
            else if ($rule['operation'] == 'is not')
                $condition = "not header :is";
            else if ($rule['operation'] == 'in')
                $condition = "header :contains";
            else if ($rule['operation'] == 'not in')
                $condition = "not header :contains";

            $text = self::sieveEscape($rule['value']);
            $text = "\"$text\"";
            $condition .= " $attribute $text";

            return $condition;
        } else if ($attribute == 'Message Size') {
            if (empty($rule['value']) || !is_numeric($rule['value']))
                throw new CException("value is not numeric");
            if (!in_array($rule['operation'], array('is', 'is not', 'less than', 'greater than')))
                throw new CException("wrong operation");

            $condition = '';
            $bytes = $rule['value'];
            if ($rule['operation'] == 'less than') {
                $condition = "size :under $bytes";
            } else if ($rule['operation'] == 'greater than') {
                $condition = "size :over $bytes";
            } else if ($rule['operation'] == 'is') {
                $bytesMinus1 = $bytes - 1;
                $bytesPlus1 = $bytes + 1;
                $condition = "allof(size :over $bytesMinus1,\n      size :under $bytesPlus1)";
            } else if ($rule['operation'] == 'is not') {
                $condition = "anyof(size :under $bytes,\n      size :over $bytes)";
            }

            return $condition;
        } else {
            throw new CException("unknown attribute $attribute");
        }
    }

    /**
     * @param $str string
     * @return string
     */
    protected static function sieveEscape($str)
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], mb_convert_encoding($str, 'UTF7-IMAP', 'UTF-8'));
    }

    /**
     * @param $condition string
     * @param $countRules int
     * @return string
     */
    protected static function alignCondition($condition, $countRules)
    {
        $conditionArr = array();
        if ($countRules > 1)
            $align = '         ';
        else
            $align = '   ';
        foreach (explode("\n", $condition) as $line) {
            $conditionArr[] = $align . $line;
        }

        return implode("\n", $conditionArr);
    }

    /**
     * @param $oldScript string
     * @param $newScript string
     * @return string
     */
    public static function mergeScripts($oldScript, $newScript)
    {
        return self::rebuildRequireHeader($oldScript . $newScript);
    }

    /**
     * @param string[] $require
     * @return string
     */
    protected static function generateRequireHeader($require)
    {
        $requireArr = array();
        foreach ($require as $requireElement) {
            $requireArr[] = "\"$requireElement\"";
        }
        $requireStr = implode(', ', $requireArr);
        if (count($requireArr) > 1)
            $requireStr = '[' . $requireStr . ']';

        return $requireStr ? "require $requireStr;\n\n" : '';
    }

    protected static function rebuildRequireHeader($script)
    {
        // delete old require headers
        $script = preg_replace('/^require.+?$\s+/ms', '', $script);

        if (preg_match_all('/^#require=(.*?)$/ms', $script, $matches)) {
            $require = array();
            foreach ($matches[1] as $match) {
                $modules = json_decode($match, true);
                $require += $modules;
            }
            $require = array_unique(array_keys($require));

            $require = SieveCreator::generateRequireHeader($require);
            $script = $require . $script;
        }

        return $script;
    }

    public static function removeRule($ruleName, $script)
    {
        $ruleName = preg_quote($ruleName, '/');
        $script = preg_replace('/^#rule=' . $ruleName . '.*?\n\n/ms', '', $script);

        return self::rebuildRequireHeader($script);
    }
}
