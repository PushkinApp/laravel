<?php

namespace Pushkin;

use PhpParser\Error;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\ParserFactory;

abstract class BaseTranslator {
    protected $input;

    protected $storage;

    protected $startLine;

    protected $endLine;

    public function __construct($input, $startLine, $endLine)
    {
        $this->input = $input;
        $this->storage = new Storage();
        $this->startLine = $startLine;
        $this->endLine = $endLine;
    }

    protected function findSource()
    {
        $currentFolder = dirname(__FILE__);
        $stack = debug_backtrace();

        $stack = array_filter($stack, function($call) use ($currentFolder) {
            return isset($call['file']) && strpos($call['file'], $currentFolder) !== 0;
        });

        array_shift($stack);
        $lastCall = array_shift($stack);

        if (preg_match('/storage\/framework\/views/', $lastCall['file'])) {
            preg_match('/\*\*PATH (.+\.blade\.php) ENDPATH\*\*/', file_get_contents($lastCall['file']), $matches);
            $path = str_replace(base_path(), '', $matches[1]);
        } else {
            $path = str_replace(base_path(), '', $lastCall['file']);
        }

        $this->source = $this->readLine($lastCall['file'], $this->startLine ?: $lastCall['line'], $this->endLine ? $this->endLine - $this->startLine : 1);

        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

        try {
            $prefix = preg_match('/<\?php/', $this->source) ? "" : "<?php\n";
            $ast = $parser->parse($prefix.$this->source);
            if (preg_match('/entries/', $this->input)) {
                dd($ast);
                $signature = $this->findSignature($ast);
            }
        } catch (Error $error) {
            //echo "Parse error: {$error->getMessage()}\n";
            //return;
        }

        return [
            'text' => $this->input,
            'file' => $path,
            'context' => $this->context($stack),
            'signature' => $signature ?? null,
            'location' => $lastCall['line']
        ];
    }

    /*
     * @return string
     */
    protected function findSignature(array $node)
    {
        $functionCall = array_filter($node, function($child) {
            return $child instanceof Echo_ && $child->exprs[0]->name->parts[0] == 'p';
        });
        if (empty($functionCall)) return;

        $functionCall = array_shift($functionCall);
        $functionCall = $functionCall->exprs[0];

        if (! $functionCall->args[0]->value instanceof Encapsed) {
            return;
        }

        return implode('', array_map(function($part) {
            if ($part instanceof EncapsedStringPart) return $part->value;
            if ($part instanceof Variable) return "{".$part->name."}";
        }, $functionCall->args[0]->value->parts));
    }

    /**
     * @param array $stack
     * @return string
     */
    protected function context(array $stack)
    {
        $lastCall = array_filter($stack, function($call) {
            return data_get($call, 'function') == 'buildMarkdownView';
        });

        if (! empty($lastCall)) {
            $lastCall = array_shift($lastCall);
            return get_class($lastCall['object']);
        }

        if (request() && request()->route()) {
            return request()->route()->getActionName();
        }
    }

    /**
     * @param $file
     * @param $lineNo
     * @param int $count
     * @return string
     */
    protected function readLine($file, $start, $count = 1)
    {
        $lines = file($file);

        return implode("\n", array_slice($lines, $start - 1, $count));
    }
}
