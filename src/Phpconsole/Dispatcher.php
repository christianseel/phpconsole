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

class Dispatcher
{
    protected $config;
    protected $client;

    public function __construct(Config &$config = null, \GuzzleHttp\Client $client = null)
    {
        $this->config = $config ?: new Config;
        $this->client = $client ?: new \GuzzleHttp\Client;
    }

    public function dispatch(Queue $queue)
    {
        $snippets = $this->prepareForDispatch($queue->flush());

        if (count($snippets) > 0) {

            $payload = array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'body' => array(
                    'version'  => Phpconsole::VERSION,
                    'type'     => 'php',
                    'snippets' => $snippets
                )
            );

            $this->client->post(
                $this->config->apiAddress,
                $payload
            );
        }
    }

    public function prepareForDispatch(array $snippets)
    {
        $snippetsAsArrays = array();

        if (count($snippets) > 0) {

            foreach ($snippets as $snippet) {

                $snippetsAsArrays[] = array(
                    'payload'           => $snippet->payload,

                    'type'              => $snippet->type,
                    'projectApiKey'     => $snippet->projectApiKey,
                    'encryptionVersion' => $snippet->encryptionVersion,
                    'isEncrypted'       => $snippet->isEncrypted,

                    'fileName'          => $snippet->fileName,
                    'lineNumber'        => $snippet->lineNumber,
                    'context'           => $snippet->context,
                    'address'           => $snippet->address,
                    'hostname'          => $snippet->hostname
                );
            }
        }

        return $snippetsAsArrays;
    }
}
