<?php
namespace Catch\Plugin\Support;


/**
 * 解析 vue 文件所有依赖文件
 */
class CollectVueDepsFile
{
    /**
     * 收集相关依赖文件
     *
     * @param string $entryPath
     * @param string $baseDir
     * @param array $collected
     * @param array $visited
     * @return array
     */
    public static function collectFilesWithDeps(string $entryPath, string $baseDir, array &$collected = [], array &$visited = []): array
    {
        // 规范化路径
        $entryPath = realpath($entryPath) ?: $entryPath;
        $baseDir = realpath($baseDir) ?: $baseDir;

        if (!file_exists($entryPath) || isset($visited[$entryPath])) {
            return $collected;
        }

        $visited[$entryPath] = true;

        $content = file_get_contents($entryPath);

        // 计算相对路径（统一使用正斜杠）
        $relativePath = str_replace('\\', '/', str_replace($baseDir, '', $entryPath));
        if (!str_starts_with($relativePath, '/')) {
            $relativePath = '/' . $relativePath;
        }

        $collected[$relativePath] = $content;

        // 解析并递归收集依赖
        $currentDir = dirname($entryPath);
        $imports = self::parseLocalImports($content, $currentDir);

        foreach ($imports as $importPath) {
            self::collectFilesWithDeps($importPath, $baseDir, $collected, $visited);
        }

        return $collected;
    }

    /**
     * 解析入口文件的相关 imports
     *
     * @param string $content
     * @param string $currentDir
     * @return array
     */
    private static function parseLocalImports(string $content, string $currentDir): array
    {
        $imports = [];

        // 匹配 import ... from './xxx' 或 import ... from '../xxx'
        // 支持单引号和双引号
        preg_match_all(
            '/from\s+[\'\"](\.\.?\/[^\'\"]+)[\'\"]/m',
            $content,
            $matches
        );

        foreach ($matches[1] as $importPath) {
            // 解析相对路径
            $resolvedPath = realpath($currentDir . '/' . $importPath);

            // 如果没有扩展名，尝试常见扩展名
            if (!$resolvedPath) {
                foreach (['.vue', '.js', '.ts', '.mjs'] as $ext) {
                    $tryPath = realpath($currentDir . '/' . $importPath . $ext);
                    if ($tryPath) {
                        $resolvedPath = $tryPath;
                        break;
                    }
                }
            }

            if ($resolvedPath && file_exists($resolvedPath)) {
                $imports[] = $resolvedPath;
            }
        }

        return $imports;
    }
}
