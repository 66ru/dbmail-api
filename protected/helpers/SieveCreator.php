<?php

class SieveCreator
{
    /**
     * @param string $ruleName
     * @param string $rulesJoinOperator
     * @param array $rules
     * @param array $actions
     * @throws CException
     * @return string
     */
    public static function generateSieveScript($ruleName, $rulesJoinOperator, $rules, $actions)
    {
        $requireArr = array();
        $ruleDisabled = false;

        $actionsString = self::getActions($actions, $requireArr);
        $conditions = self::getConditions($rules, $requireArr);
        $require = self::generateRequireHeader(array_keys($requireArr));
        foreach ($rules as $rule) {
            if (isset($rule['Disabled'])) {
                $ruleDisabled = true;
            }
        }

        $actionsString = implode(";\n    ", $actionsString);

        // align conditions
        $conditions = implode(",\n", $conditions);
        $conditionsArr = array();
        foreach (explode("\n", $conditions) as $condition) {
            $conditionsArr[] = empty($conditionsArr) ? $condition : self::alignCondition($condition, count($rules));
        }
        $conditions = implode("\n", $conditionsArr);

        if ($actionsString && $conditions) {
            $sieve = $require . "#rule=$ruleName\n";
            if (!empty($requireArr)) {
                $sieve .= '#require=' . json_encode($requireArr) . "\n";
            }
            $sieve .= '#rules=' . json_encode($rules) . "\n";
            $sieve .= '#rulesJoinOperator=' . json_encode($rulesJoinOperator) . "\n";
            $sieve .= '#actions=' . json_encode($actions) . "\n";

            if (count($rules) > 1) {
                if ($rulesJoinOperator == 'and') {
                    $conditions = "allof($conditions)";
                } elseif ($rulesJoinOperator == 'or') {
                    $conditions = "anyof($conditions)";
                } else {
                    throw new CException("rulesJoinOperator must be in [and, or]");
                }
            }

            $sieveRule = "if $conditions {\n    $actionsString;\n}\n\n";
            if ($ruleDisabled) {
                $sieveRule = preg_replace('/^(?!$)/m', '#', $sieveRule);
            }
            $sieve .= $sieveRule;
            return $sieve;
        } else {
            return '';
        }
    }

    /**
     * @param array $actions
     * @param string[] $require
     * @throws CException
     * @return string[]
     */
    protected static function getActions($actions, &$require)
    {
        $actionsArr = array();
        foreach ($actions as $action => $attribute) {
            if ($action == 'Discard') {
                $actionsArr[] = 'discard';
            } elseif ($action == 'Mirror to') {
                if (!is_string($attribute) || !preg_match('#^.+?@.+?\..+?$#', $attribute)) {
                    throw new CException("wrong email $attribute");
                }
                $attribute = self::sieveEscape($attribute);
                $actionsArr[] = "redirect \"$attribute\"";
            } elseif ($action == 'Mark') {
                if (!is_string($attribute) || !in_array($attribute, array('Flagged', 'Read'))) {
                    throw new CException("wrong attribute $attribute");
                }

                $require['imap4flags'] = true;
                $action = 'keep :flags ';
                if ($attribute == 'Flagged') {
                    $action .= ' "Flagged"'; // todo: dbmail 3.0.2 bug. must be $action .= ' "\\\\Flagged"';
                } elseif ($attribute == 'Read') {
                    $action .= ' "Seen"'; // todo: dbmail 3.0.2 bug. $action .= ' "\\\\Seen"';
                }
                $actionsArr[] = $action;
            } elseif ($action == 'Store in') {
                if (empty($attribute) || !is_string($attribute)) {
                    throw new CException("wrong attribute");
                }
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
        if (!isset($rules[0])) {
            $rules = array($rules);
        }
        foreach ($rules as $rule) {
            foreach ($rule as $attribute => $condition) {
                $condition = self::getCondition($attribute, $condition, $require);
                if ($condition) {
                    $conditions[] = $condition;
                }
            }
        }

        return $conditions;
    }

    /**
     * @param $attribute string
     * @param $rule array
     * @param string[] $require
     * @throws CException
     * @return string my be empty
     */
    protected static function getCondition($attribute, $rule, &$require)
    {
        if (in_array($attribute, array('From', 'Subject', 'Any To or Cc', 'X-Spam-Flag'))) {
            if (empty($rule['value']) || empty($rule['operation']) ||
                !is_string($rule['value']) || !is_string($rule['operation'])
            ) {
                throw new CException("value or operation are wrong");
            }
            if (!in_array($rule['operation'], array('is', 'is not'))) {
                throw new CException("operation wrong");
            }

            // if value surrounded by asterisks - convert operation to internal kind
            if (strpos($rule['value'], '*') === 0 && strrpos($rule['value'], '*') === strlen($rule['value']) - 1) {
                if ($rule['operation'] == 'is') {
                    $rule['operation'] = 'in';
                    $rule['value'] = trim($rule['value'], '*');
                } elseif ($rule['operation'] == 'is not') {
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
            if ($rule['operation'] == 'is') {
                $condition = "header :is";
            } elseif ($rule['operation'] == 'is not') {
                $condition = "not header :is";
            } elseif ($rule['operation'] == 'in') {
                $condition = "header :contains";
            } elseif ($rule['operation'] == 'not in') {
                $condition = "not header :contains";
            }

            $text = self::sieveEscape($rule['value']);
            $text = "\"$text\"";
            $condition .= " $attribute $text";

            return $condition;
        } elseif ($attribute == 'Message Size') {
            if (empty($rule['value']) || !is_numeric($rule['value'])) {
                throw new CException("value is not numeric");
            }
            if (!in_array($rule['operation'], array('is', 'is not', 'less than', 'greater than'))) {
                throw new CException("wrong operation");
            }

            $condition = '';
            $bytes = $rule['value'];
            if ($rule['operation'] == 'less than') {
                $condition = "size :under $bytes";
            } elseif ($rule['operation'] == 'greater than') {
                $condition = "size :over $bytes";
            } elseif ($rule['operation'] == 'is') {
                $bytesMinus1 = $bytes - 1;
                $bytesPlus1 = $bytes + 1;
                $condition = "allof(size :over $bytesMinus1,\n      size :under $bytesPlus1)";
            } elseif ($rule['operation'] == 'is not') {
                $condition = "anyof(size :under $bytes,\n      size :over $bytes)";
            }

            return $condition;
        } elseif ($attribute == 'Disabled') {
            return ''; // allowed attribute
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
        return str_replace(array('\\', '"'), array('\\\\', '\\"'), mb_convert_encoding($str, 'UTF7-IMAP', 'UTF-8'));
    }

    /**
     * @param $condition string
     * @param $countRules int
     * @return string
     */
    protected static function alignCondition($condition, $countRules)
    {
        $conditionArr = array();
        if ($countRules > 1) {
            $align = str_repeat(' ', 9);
        } else {
            $align = str_repeat(' ', 3);
        }
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
        if (count($requireArr) > 1) {
            $requireStr = '[' . $requireStr . ']';
        }

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
