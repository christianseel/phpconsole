<?php

/**
 * A detached logging facility for PHP to aid your daily development routine.
 *
 * Watch quick tutorial at: https://vimeo.com/58393977
 *
 * @link http://phpconsole.com
 * @link https://github.com/phpconsole
 * @copyright Copyright (c) 2012 - 2014 phpconsole.com
 * @license See LICENSE file
 * @version 2.0.1
 */

namespace Phpconsole;

class Snippet
{
    protected $config;
    protected $metadataWrapper;
    protected $encryptor;

    public $payload;

    public $type;
    public $project;
    public $projectApiKey;
    public $encryptionVersion;
    public $isEncrypted = false;

    public $fileName;
    public $lineNumber;
    public $context;
    public $address;
    public $hostname;

    public function __construct(Config &$config = null, MetadataWrapper $metadataWrapper = null, Encryptor $encryptor = null)
    {
        $this->config          = $config          ?: new Config;
        $this->metadataWrapper = $metadataWrapper ?: new MetadataWrapper($this->config);
        $this->encryptor       = $encryptor       ?: new Encryptor($this->config);
    }

    public function setPayload($payload)
    {
        $this->payload = $this->preparePayload($payload);
    }

    public function setOptions($options)
    {
        $options = $this->prepareOptions($options);

        $this->type    = $options['type'];
        $this->project = $options['project'];

        $this->projectApiKey = $this->config->getApiKeyFor($this->project);
    }

    public function setMetadata()
    {
        $bt               = $this->metadataWrapper->debugBacktrace();
        $backtraceDepth   = $this->config->backtraceDepth;
        $fileName         = $bt[$backtraceDepth]['file'];
        $lineNumber       = $bt[$backtraceDepth]['line'];

        $this->fileName   = base64_encode($fileName);
        $this->lineNumber = base64_encode($lineNumber);
        $this->context    = base64_encode($this->readContext($fileName, $lineNumber));
        $this->address    = base64_encode($this->currentPageAddress());
        $this->hostname   = base64_encode($this->metadataWrapper->gethostname());
    }

    public function encrypt()
    {
        $password = $this->config->getEncryptionPasswordFor($this->project);

        if ($password !== null) {

            $this->encryptor->setPassword($password);

            $this->payload    = base64_decode($this->payload);
            $this->fileName   = base64_decode($this->fileName);
            $this->lineNumber = base64_decode($this->lineNumber);
            $this->context    = base64_decode($this->context);
            $this->address    = base64_decode($this->address);
            $this->hostname   = base64_decode($this->hostname);

            $this->payload    = $this->encryptor->encrypt($this->payload);
            $this->fileName   = $this->encryptor->encrypt($this->fileName);
            $this->lineNumber = $this->encryptor->encrypt($this->lineNumber);
            $this->context    = $this->encryptor->encrypt($this->context);
            $this->address    = $this->encryptor->encrypt($this->address);
            $this->hostname   = $this->encryptor->encrypt($this->hostname);

            $this->encryptionVersion = $this->encryptor->getVersion();
            $this->isEncrypted = true;
        }
    }

    protected function preparePayload($payload)
    {
        $payload = $this->replaceTrueFalseNull($payload);
        $payload = print_r($payload, true);
        $payload = base64_encode($payload);

        return $payload;
    }

    protected function prepareOptions($options)
    {
        if (is_string($options)) {
            $options = array('project' => $options);
        }

        if (!isset($options['project'])) {
            $options['project'] = $this->config->defaultProject;
        }

        if (!isset($options['type'])) {
            $options['type'] = 'normal';
        }

        return $options;
    }

    protected function replaceTrueFalseNull($input)
    {
        if (is_array($input)) {
            if (count($input) > 0) {
                foreach ($input as $key => $value) {
                    $input[$key] = $this->replaceTrueFalseNull($value);
                }
            }
        } elseif (is_object($input)) {
            if (count($input) > 0) {
                foreach ($input as $key => $value) {
                    $input->$key = $this->replaceTrueFalseNull($value);
                }
            }
        }

        if ($input === true) {
            $input = 'true';
        } elseif ($input === false) {
            $input = 'false';
        } elseif ($input === null) {
            $input = 'null';
        }

        return $input;
    }

    protected function readContext($fileName, $lineNumber)
    {
        $context = array();

        if ($this->config->isContextEnabled && function_exists('file')) {

            $file = $this->metadataWrapper->file($fileName);
            $contextSize = $this->config->contextSize;

            $contextFrom = $lineNumber - $contextSize - 1;
            $contextTo   = $lineNumber + $contextSize - 1;

            for ($i = $contextFrom; $i <= $contextTo; $i++) {

                if ($i < 0 || $i >= count($file)) {
                    $context[] = '';
                } else {
                    $context[] = $file[$i];
                }
            }
        }

        return json_encode($context);
    }

    protected function currentPageAddress()
    {
        $server = $this->metadataWrapper->server();

        if (isset($server['HTTPS']) && $server['HTTPS'] == 'on') {
            $address = 'https://';
        } else {
            $address = 'http://';
        }

        if (isset($server['HTTP_HOST'])) {
            $address .= $server['HTTP_HOST'];
        }

        if (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] != '80') {

            $port = $server['SERVER_PORT'];
            $address_end = substr($address, -1*(strlen($port)+1));

            if ($address_end !== ':'.$port) {
                $address .= ':'.$port;
            }
        }

        if (isset($server['REQUEST_URI'])) {
            $address .= $server['REQUEST_URI'];
        }

        return $address;
    }
}
