<?php

namespace flexibuild\file\contexts;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

use flexibuild\file\helpers\FileSystemHelper;
use flexibuild\file\storages\StorageInterface;
use flexibuild\file\storages\Storage;
use flexibuild\file\formatters\FormatterInterface;
use flexibuild\file\formatters\Formatter;
use flexibuild\file\formatters\FromFormatter;

/**
 * Horizontally separated context's logic for formatters.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property string $name name of context.
 * @method StorageInterface getStorage() context's storage.
 * @property-read StorageInterface $storage context's storage.
 */
trait ContextFormattersTrait
{
    /**
     * You can use this param for setting your formatters short aliases and use the last in `$formatters` config.
     * @var array in alias => string|array formatter config (in Yii style).
     */
    public $formattersAliases = [];

    /**
     * @var array
     */
    private $_formatters = [];

    /**
     * Gets context's formatters.
     * @return FormatterInterface[]
     */
    public function getFormatters()
    {
        $result = [];
        foreach ($this->formatterNames() as $name) {
            $result[$name] = $this->getFormatter($name);
        }
        return $result;
    }

    /**
     * Sets context's file formatters. You can use Yii config standarts.
     * @param array $formatters array each element of which is formatter object or formatter config.
     * 
     * You may use some helpful syntax for defining formatters. Examples:
     * ```php
     *  [
     *      'format_name_1' => 'YOUR\FORMATTER\CLASS\NAME',
     *      'format_name_2' => 'built-in-name',
     *      'format_name_3' => [
     *          'YOUR\FORMATTER\CLASS\NAME',
     *          'param1' => 'value1',
     *          'param2' => 'value2',
     *      ],
     *      'format_name_4' => [
     *          'class' => 'YOUR\FORMATTER\CLASS\NAME',
     *          'param1' => 'value1',
     *          'param2' => 'value2',
     *      ],
     *      'format_name_5' => ['built-in-name', 'param1' => 'value1', 'param2' => 'value2'],
     *      'format_name_6' => function ($readFilePath, $formatter) {
     *          // your logic, where
     *          // - $readFilePath: string, path to file, that must be formatted,
     *          // - $formatter: InlineFormatter, instance of self formatter object.
     *      },
     * 
     *      // built-in concrete examples
     *      'format_name_7' => ['from', 'from' => 'format_name_x', 'formatter' => [
     *          // formatter configuration in a similar way, for example:
     *          'YOUR\FORMATTER\CLASS\NAME',
     *      ]]
     *      'format_name_8' => ['chain', 'formatters' => [
     *          // formatters array, each element is a configuration in a similar way, for example:
     *          'YOUR\FORMATTER\CLASS\NAME1',
     *          'YOUR\FORMATTER\CLASS\NAME2',
     *      ]],
     *  ]
     * ```
     */
    public function setFormatters($formatters)
    {
        if (!is_array($formatters)) {
            throw new InvalidConfigException('Param $formatters must be an array.');
        }
        foreach ($formatters as $name => $formatter) {
            if (!preg_match('/^[0-9a-z\_]+$/i', $name)) {
                throw new InvalidConfigException("Incorrect name '$name', the name of formatter may have digits, letters or underscope only.");
            }
        }
        $this->_formatters = $formatters;
    }

    /**
     * Checks existing of formatter by name.
     * @param string $name
     * @return bool whether formatter with `$name` exists.
     */
    public function hasFormatter($name)
    {
        return isset($this->_formatters[$name]);
    }

    /**
     * Returns formatter object by name.
     * @param string $name
     * @return FormatterInterface the formatter with name === `$name`.
     * @throws InvalidParamException if formatter with the name is not defined.
     */
    public function getFormatter($name)
    {
        if (!isset($this->_formatters[$name])) {
            throw new InvalidParamException("Cannot find formatter with name '$name'.");
        }

        $formatter = $this->_formatters[$name];
        return $this->_formatters[$name] = $this->instantiateFormatter($formatter, $name);
    }

    /**
     * Creates formatter by config.
     * @param mixed $formatter an instance of [[FormatterInterface]] or config for creating it.
     * @param string|null $name the name of formatter. Null meaning the formatter has not a name (by default).
     * @return FormatterInterface created formatter.
     * @throws InvalidConfigException if formatter has incorrect format.
     */
    public function instantiateFormatter($formatter, $name = null)
    {
        if ($formatter instanceof FormatterInterface) {
            return $formatter;
        }

        if ($formatter instanceof \Closure) {
            $formatter = ['inline', 'format' => $formatter];
        } elseif (is_string($formatter)) {
            $formatter = [$formatter];
        }

        if (is_array($formatter)) {
            foreach ([$this->formattersAliases, static::$builtInFormatters] as $aliases) {
                if (isset($formatter[0]) && isset($aliases[$formatter[0]])) {
                    $formatter = array_replace((array) $aliases[$formatter[0]], array_slice($formatter, 1, null, true));
                }
            }
            if (isset($formatter[0])) {
                $className = $formatter[0];
                unset($formatter[0]);
                $formatter = array_merge(['class' => $className], $formatter);
            }
        }

        $formatter = Yii::createObject($formatter);
        if ($formatter instanceof Formatter) {
            if ($formatter->context === null) {
                $formatter->context = $this;
            }
            if ($name !== null && $formatter->name === null) {
                $formatter->name = $name;
            }
        } elseif (!$formatter instanceof FormatterInterface) {
            if ($name === null) {
                throw new InvalidConfigException('Cannot create formatter, it must be an instance or config for creating an instance of flexibuid/formatters/FormatterInterface.');
            } else {
                throw new InvalidConfigException("Cannot create formatter '$name', it must be an instance or config for creating an instance of flexibuid/formatters/FormatterInterface.");
            }
        }

        return $formatter;
    }

    /**
     * @var mixed FormatterInterface or null, or config for creating formatter.
     */
    private $_defaultFormatter;

    /**
     * Instantiate and returns default formatter object.
     * See documentation of [[self::$defaultFormatter]] for more info.
     * @return FormatterInterface|null context's default formatter. Null meaning formatter has not default formatter.
     */
    public function getDefaultFormatter()
    {
        if ($this->_defaultFormatter === null) {
            return null;
        }
        $this->_defaultFormatter = $this->instantiateFormatter($this->_defaultFormatter);
        return $this->_defaultFormatter;
    }

    /**
     * Sets config of default formatter.
     * See documentation of [[self::$defaultFormatter]] for more info.
     * @param mixed $defaultFormatter either FormatterInterface instance or null, or config for creating formatter.
     */
    public function setDefaultFormatter($defaultFormatter)
    {
        $this->_defaultFormatter = $defaultFormatter;
    }

    /**
     * Generates formatted version of file.
     * @param string $data string data that can be used for manipulating with file.
     * @param string $format name of format that must be generated.
     * @param boolean $regenerate whether formatted version of file must be regenerated.
     * If false and formatted version exists the method will not start generate process.
     * @return $data new data that can be used for manipulating with file.
     * @throws InvalidConfigException if formatter has invalid config.
     */
    public function generateFormat($data, $format, $regenerate = false)
    {
        list($newData, $formattedTmpFile, $canUnlink) = $this->generateFormatInternal($data, $format, $regenerate);
        if ($canUnlink && !@unlink($formattedTmpFile)) {
            Yii::warning("Cannot remove temporary formatted file '$formattedTmpFile'.", __METHOD__);
        }
        return $newData;
    }

    /**
     * Generates set of formatters. This method optimized formats generating according to [[FormatterFrom]] formatters.
     * 
     * @param string $data string data that can be used for manipulating with file.
     * @param array $formats array names of formats that must be generated.
     * Null meaning all formats will be generated.
     * @param boolean $regenerate whether formatted version of file must be regenerated.
     * If false and formatted version exists the method will not start generate process.
     * @return string data that can be used for manipulating with file.
     * @throws InvalidConfigException if cannot save file or formatters have invalid config.
     */
    public function generateFormats($data, $formats = null, $regenerate = false)
    {
        if ($formats === null) {
            $formats = $this->formatterNames();
        }
        if (!count($formats)) {
            return $data;
        }

        $formats = array_unique($formats);
        $formats = array_combine($formats, $formats);
        $preparedFormats = [];

        while (count($formats)) {
            $formattersToGenerate = [];
            $firstBadFormatter = null;
            foreach ($formats as $format) {
                $formatter = $this->getFormatter($format);
                if (!$formatter instanceof FromFormatter) {
                    $formattersToGenerate[] = $format;
                } elseif (isset($preparedFormats[$formatter->from]) || !isset($formats[$formatter->from])) {
                    $formattersToGenerate[] = $format;
                } elseif ($firstBadFormatter === null) {
                    $firstBadFormatter = $formatter;
                }
            }

            if (!count($formattersToGenerate)) {
                /* @var $firstBadFormatter FromFormatter */ // will isset here
                throw new InvalidConfigException("Formatter '$firstBadFormatter->name' of '{$this->name}' context has a cycle in own formatters hierarchy.");
            }

            foreach ($formattersToGenerate as $format) {
                $formatter = $this->getFormatter($format);
                if (!$formatter instanceof FromFormatter) {
                    list($data, $tmpFile, $canUnlink) = $this->generateFormatInternal($data, $format, $regenerate);
                    $preparedFormats[$format] = [$tmpFile, $canUnlink];
                } else {
                    if (!isset($preparedFormats[$formatter->from])) {
                        list($data, $tmpFile, $canUnlink) = $this->generateFormatInternal($data, $formatter->from, false);
                        $preparedFormats[$formatter->from] = [$tmpFile, $canUnlink];
                    }
                    list($data, $tmpFile, $canUnlink) = $this->generateFormatInternal($data, $format, $regenerate, $preparedFormats[$formatter->from][0]);
                    $preparedFormats[$format] = [$tmpFile, $canUnlink];
                }
                unset($formats[$format]);
            }
        }

        foreach ($preparedFormats as $format => $tmpFileData) {
            list($tmpFileName, $canUnlink) = $tmpFileData;
            if ($canUnlink && !@unlink($tmpFileName)) {
                Yii::warning("Cannot remove temporary formatted file '$tmpFileName'.", __METHOD__);
            }
        }
        return $data;
    }

    /**
     * @internal internally used in [[self::generateFormat()]] & [[self::generateFormats()]].
     * Generates formatted version of file.
     * 
     * @param string $data string data that can be used for manipulating with file.
     * @param string $format the name of format.
     * @param boolean $regenerate whether formatted file must be generated even if it exists.
     * @param string|null $tmpNameDependentFile path to tmp filename of dependent formatted version.
     * It's used only for [[FromFormatter]]. 'Dependent' means the temp filename of [[FromFormatter::$from]] file.
     * 
     * @return array array that contains 2 elements:
     * 
     * - string (index 0), new data that can be used for manipulating with file.
     * - string (index 1), temp file path to the new formatted version of file.
     * - boolean (index 2), whether temp file can be unlinked.
     * 
     * @throws InvalidConfigException if cannot save file.
     */
    protected function generateFormatInternal($data, $format, $regenerate = false, $tmpNameDependentFile = null)
    {
        $storage = $this->getStorage();
        if (!$regenerate && $storage->fileExists($data, $format)) {
            return [$data, $storage->getReadFilePath($data, $format), false];
        }

        $formatter = $this->getFormatter($format);
        if ($formatter instanceof FromFormatter && ($tmpNameDependentFile !== null || $storage->fileExists($data, $formatter->from))) {
            if ($tmpNameDependentFile === null) {
                $tmpNameDependentFile = $storage->getReadFilePath($data, $formatter->from);
            }
            $formattedTmpFile = $formatter->format($tmpNameDependentFile, true);
        } else {
            $readFilePath = $storage->getReadFilePath($data);
            $formattedTmpFile = $formatter->format($readFilePath);
        }

        if ($storage instanceof Storage) {
            $resultData = $storage->saveFormattedFileByCopying($data, $formattedTmpFile, $format);
        } else {
            if (false === $content = @file_get_contents($formattedTmpFile)) {
                Yii::warning("Cannot read content of the file '$formattedTmpFile'.", __METHOD__);
                $resultData = false;
            } else {
                $decodedFromattedTmpFile = FileSystemHelper::decodeFilename($formattedTmpFile);
                $formattedTmpFileExt = $decodedFromattedTmpFile === false
                    ? false
                    : FileSystemHelper::extension($decodedFromattedTmpFile);
                $resultData = $storage->saveFormattedFile($data, $content, $format, $formattedTmpFileExt);
            }
        }

        if ($resultData === false) {
            throw new InvalidConfigException("Cannot save formatted as '$format' file for '$data' in the '{$this->name}' context.");
        }
        return [$resultData, $formattedTmpFile, true];
    }

    /**
     * @return array list all formatters names.
     */
    public function formatterNames()
    {
        return array_keys($this->_formatters);
    }
}
