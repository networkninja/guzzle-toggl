<?php

namespace AJT\Toggl;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Result;

/**
 * A TogglClient
 */
class TogglClient extends GuzzleClient
{

    /**
     * Factory method to create a new TogglClient
     *
     * The following array keys and values are available options:
     * - username: username or API key
     * - password: password (if empty, then username is a API key)
     *
     * See https://www.toggl.com/public/api#api_token for more information on the api token
     *
     * @param array $config Configuration data
     * @return TogglClient
     * @throws \Exception
     */
    public static function factory(array $config = [])
    {
        $guzzleClient = new Client(self::getClientConfig($config));

        if (isset($config['apiVersion']) && $config['apiVersion'] !== 'v9') {
            throw new InvalidApiVersionException('Only v9 is supported at this time');
        }

        return new self($guzzleClient, self::getAPIDescriptionByJsonFile('services_v9.json'));
    }

    protected static function getAPIDescriptionByJsonFile($file): Description
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Service description "%s" not found.', $file));
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(
                sprintf('Error parsing JSON in "%s" - %s', $file, json_last_error_msg())
            );
        }

        return new Description($data);
    }

    protected static function getClientConfig($config): array
    {
        $clientConfig = [];

        if (isset($config['debug']) && is_bool($config['debug'])) {
            $clientConfig['debug'] = $config['debug'];
        }

        if (isset($config['api_key'])) {
            $clientConfig['auth'] = [
                $config['api_key'],
                'api_token',
            ];
            return $clientConfig;
        }
        if (isset($config['username'])) {
            if (!isset($config['password'])) {
                $config['password'] = 'api_token';
            }

            $clientConfig['auth'] = [
                $config['username'],
                $config['password'],
            ];
            return $clientConfig;
        }

        throw new AuthenticationDetailsMissingException('Provide authentication details');
    }

    /**
     * Shortcut for executing Commands in the Definitions.
     *
     * @param string $method
     * @param array|null $args
     *
     * @return mixed|void
     */
    public function __call($method, array $args)
    {
        $commandName = ucfirst($method);
        /** @var Result $result */
        $result = parent::__call($commandName, $args);
        // Remove data field
        if (is_array($result) && isset($result['data'])) {
            return $result['data'];
        }

        return $result;
    }
}