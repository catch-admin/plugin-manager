<?php
namespace Catch\Plugin\Support;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class ComposerAuth
{
    /**
     * @param string $domain
     * @param string $token
     * @return true
     * @throws FileNotFoundException
     */
    public function token(string $domain, string $token): true
    {
        $authJson = $this->getAuthJson();

        $authJson['bearer'] = array_merge([parse_url($domain, PHP_URL_HOST) => $token], $authJson['bearer'] ?? []);

        File::put(base_path('auth.json'), json_encode($authJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        return true;
    }


    /**
     * @return array
     * @throws FileNotFoundException
     */
    protected function getAuthJson(): array
    {
        $authJson = base_path('auth.json');

        if (! File::exists($authJson)) {
            return [];
        }

        return json_decode(File::get($authJson), true);
    }
}
