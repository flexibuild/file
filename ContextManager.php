<?php

namespace flexibuild\file;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

use flexibuild\file\contexts\Context;

/**
 * Context Manager for your yii2 application.
 * Main idea of this class is keeping all file contexts in one place (app config).
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property Context[] $contexts array of all contexts in context name => context object format.
 * Each value of this array may have standard Yii config style, because it will be passed in [[Yii::createObject()]] method.
 * 
 */
class ContextManager extends Component
{
    /**
     * !!! write examples for this built-in aliases.
     * Aliases for context types. You can use it instead of full class paths.
     * @var array of built-in context aliases in alias => config format.
     */
    static $builtInContexts = [
        'default' => 'flexibuild\file\contexts\Context',
        'image' => 'flexibuild\file\contexts\ImageContext',
        'pdf' => 'flexibuild\file\contexts\PdfContext',
    ];

    /**
     * @var string|array default config for creating context.
     * This property will be used by default for creating contexts.
     */
    public $defaultContext = 'flexibuild\file\contexts\Context';

    /**
     * @var array array of contexts config or context objects.
     */
    private $_contexts = [];

    /**
     * Checks whether the manager has context with `$name`.
     * @param string $name the name of context that must be checked.
     * @return boolean whether the manager has context with name equal to `$name`.
     */
    public function hasContext($name)
    {
        return isset($this->_contexts[$name]);
    }

    /**
     * Returns context object by its name.
     * @param string $name the name of context that must be returned.
     * @return Context the context instance.
     * @throws InvalidParamException if context does not exist.
     * @throws InvalidConfigException if context has invalid config.
     */
    public function getContext($name)
    {
        if (!isset($this->_contexts[$name])) {
            throw new InvalidParamException("Context with name $name does not exist.");
        }
        $context = $this->_contexts[$name];
        return $this->_contexts[$name] = $this->instantiateContext($context, $name);
    }

    /**
     * Creates context object if context config passed to `$context` param.
     * @param mixed $context the context object or config for creating it.
     * @param string $name the name of context.
     * @return Context instantiated instance of Context.
     * @throws InvalidConfigException if context has invalid config.
     */
    protected function instantiateContext($context, $name)
    {
        if (!is_object($context)) {
            if (is_string($context)) {
                $context = [$context];
            }
            if (is_array($context) && isset($context[0])) {
                if (isset(static::$builtInContexts[$context[0]])) {
                    $context = array_replace((array) static::$builtInContexts[$context[0]], array_slice($context, 1, null, true));
                }
                if (isset($context[0])) {
                    $className = $context[0];
                    unset($context[0]);
                    $context = array_merge(['class' => $className], $context);
                }
            }
            if (is_array($context)) {
                $defaultContext = is_array($this->defaultContext) ? $this->defaultContext : ['class' => $this->defaultContext];
                $context = array_merge($defaultContext, $context);
            }
            $context = Yii::createObject($context);
        }
        if (!$context instanceof Context) {
            throw new InvalidConfigException("Context '$name' has invalid config. It must be an instance of " . Context::className() . ' or a config for creating it.');
        }
        if ($context->name === null) {
            $context->name = $name;
        }
        return $context;
    }

    /**
     * Returns list of all contexts.
     * @return Context[] array of all contexts in context name => context object format.
     * @throws InvalidConfigException if a context has invalid config.
     */
    public function getContexts()
    {
        foreach ($this->_contexts as $name => $contextCfg) {
            $this->_contexts[$name] = $this->instantiateContext($contextCfg, $name);
        }
        return $this->_contexts;
    }

    /**
     * Sets contexts configuration.
     * @param array $contexts array of contexts configs in context name => context config or object format.
     * Each value of this array must have standard Yii config style, because it will be passed in [[Yii::createObject()]] method.
     * @throws InvalidConfigException if `$contexts` has invalid format.
     */
    public function setContexts($contexts)
    {
        if (!is_array($contexts)) {
            throw new InvalidConfigException('Param $contexts must be an array.');
        }
        foreach ($contexts as $name => $contextConfig) {
            if (!preg_match('/^[a-z0-9\-\_]+$/i', $name)) {
                throw new InvalidConfigException("Context name '$name' has invalid format. Name can consists of letters, digits, underscore or dash only.");
            }
        }
        $this->_contexts = $contexts;
    }
}
