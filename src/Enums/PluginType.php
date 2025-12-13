<?php
namespace Catch\Plugin\Enums;

enum PluginType: string
{
    case Library = 'library';
    case Plugin = 'plugin';
    case CatchadminPlugin = 'catchadmin-plugin';
    case Module = 'module';
    case Project = 'project';

    /**
     * @return bool
     */
    public function isLibrary(): bool
    {
        return $this->value === self::Library->value;
    }

    public function isPlugin(): bool
    {
        return $this->value === self::Plugin->value;
    }

    public function isCatchadminPlugin(): bool
    {
        return $this->value === self::CatchadminPlugin->value;
    }

    public function isModule(): bool
    {
        return $this->value === self::Module->value;
    }

    public function isProject(): bool
    {
        return $this->value === self::Project->value;
    }

    /**
     * 是否支持 Composer 安装/卸载
     */
    public function supportsComposer(): bool
    {
        return in_array($this->value, [
            self::Library->value,
            self::Plugin->value,
            self::CatchadminPlugin->value,
        ]);
    }
}
