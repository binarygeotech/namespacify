<?php

/**
 * Psr0Generator
 *
 * @category  generator
 * @package   namespacify
 * @author    Florian Eckerstorfer <florian@theroadtojoy.at>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @copyright 2012 2bePUBLISHED Internet Services Austria GmbH
 */

namespace Pub\Namespacify\Generator;

use Symfony\Component\Filesystem\Filesystem;

use Pub\Namespacify\Index\ParsedIndex;
use Pub\Namespacify\Transformer\TransformerInterface;

/**
 * Psr0Generator
 *
 * @category  generator
 * @package   namespacify
 * @author    Florian Eckerstorfer <florian@theroadtojoy.at>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @copyright 2012 2bePUBLISHED Internet Services Austria GmbH
 */
class Psr0Generator implements GeneratorInterface
{
    /** @var Symfony\Component\Filesystem\Filesystem */
    protected $filesystem;

    /** @var callback */
    protected $loggingCallback;

    /** @var Pub\Namespacify\Transformer\TransformerInterface */
    protected $transformer;

    /** @var \Closure */
    protected $writer;

    /**
     * Sets the file system.
     *
     * @param Symfony\Component\Filesystem\Filesystem $filesystem Filesystem
     *
     * @return Pub\Namespacify\Generator\Psr0Generator
     */
    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        return $this;
    }

    /**
     * Returns the Filesystem.
     *
     * @return Symfony\Component\Filesystem\Filesystem The Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * Sets the logging callback function.
     *
     * @param \Closure $loggingCallback The logging callback
     *
     * @return \Pub\Namespacify\Generator\Psr0Generator
     */
    public function setLoggingCallback(\Closure $loggingCallback)
    {
        $this->loggingCallback = $loggingCallback;
        return $this;
    }

    /**
     * Sets the writer.
     *
     * Writer is the function that writes to the file system.
     *
     * @param \Closure $writer The writer closure
     *
     * @return \Pub\Namespacify\Generator\Psr0Generator
     */
    public function setWriter(\Closure $writer)
    {
        $this->writer = $writer;
        return $this;
    }

    /**
     * Returns the writer. If no writer is specified, returns a default writer.
     *
     * @return \Closure The writer clojure
     */
    public function getWriter()
    {
        if (!$this->writer) {
            // @codeCoverageIgnoreStart
            return function ($file, $code) {
                file_put_contents($file, $code);
            };
            // @codeCoverageIgnoreEnd
        }
        return $this->writer;
    }

    /**
     * Sets the transformer.
     *
     * @param \Pub\Namespacify\Generator\Psr0Generator $transformer The transformer
     *
     * @return \Pub\Namespacify\Generator\Psr0Generator
     */
    public function setTransformer(TransformerInterface $transformer)
    {
        $this->transformer = $transformer;
        return $this;
    }

    /** {@inheritDoc} */
    public function generate(ParsedIndex $index, $outputDir)
    {
        // Remove trailing slash
        $outputDir = preg_replace('/\/$/', '', $outputDir);

        foreach ($index->getAll() as $class) {
            // Search which classes are used in this class and "use" statements for these classes
            $useStatements = $this->generateUseStatements($index, $class['code']);

            // Generate the code for the file
            $codeTemplate = "<?php\n\nnamespace %s;\n%s\n%s\n";
            $class['code'] = sprintf($codeTemplate, $class['namespace'], $useStatements, $class['code']);

            // Apply the transformer
            if ($this->transformer) {
                $class = $this->transformer->transform($class);
            }

            // Generate the directory (if required)
            $dir = $outputDir . '/' . str_replace('\\', '/', $class['namespace']);
            $this->filesystem->mkdir($dir);
            $file = $dir . '/' . $class['class'] . '.php';
            call_user_func($this->getWriter(), $file, $class['code']);

            // Loggging
            if ($this->loggingCallback) {
                call_user_func($this->loggingCallback, $class['namespace'], $class['class'], $file);
            }
        }

        return $this;
    }

    /**
     * Generates the required "use" statements based on the given code and index.
     *
     * @param \Pub\Namespacify\Index\ParsedIndex $index The parsed index
     * @param string                             $code  The code
     *
     * @return string The "use" statements
     */
    protected function generateUseStatements(ParsedIndex $index, $code)
    {
        $useStatementTemplate = "use %s\\%s;\n";

        $useStatements = '';
        if (preg_match_all('/(new (([a-zA-Z0-9-]+)\()|([a-zA-Z0-9_]+)::)/', $code, $matches)) {
            $matches = array_unique(array_merge($matches[3], $matches[4]));
            foreach ($matches as $match) {
                $match = trim($match);
                if (strlen($match) > 0 && 'parent' !== $match && 'self' !== $match) {
                    if ($index->has($match)) {
                        $class = $index->get($match);
                    } else {
                        $class = array(
                            'namespace' => '',
                            'class'     => $match
                        );
                    }
                    $useStatements .= sprintf($useStatementTemplate, $class['namespace'], $class['class']);
                }
            }
        }
        /*foreach ($index->getAll() as $class) {
            $classOccurencePattern = sprintf('/(new %s|%s::)/', $class['class'], $class['class']);
            if (preg_match($classOccurencePattern, $code)) {
                $useStatements .= sprintf($useStatementTemplate, $class['namespace'], $class['class']);
            }
        }*/
        return strlen($useStatements) > 0 ? "\n" . $useStatements : "";
    }
}