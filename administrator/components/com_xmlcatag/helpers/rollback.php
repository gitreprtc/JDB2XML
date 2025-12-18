<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class XmlcatagRollbackHelper
{
    public static function run(): string
    {
        $cacheFile = JPATH_ADMINISTRATOR . '/cache/xmlcatag_last_rollback.txt';
        if (!file_exists($cacheFile)) {
            return 'Geen rollback-log gevonden.';
        }

        $basename = trim(file_get_contents($cacheFile));
        if ($basename === '') return 'Geen rollback-log gevonden.';

        $file = JPATH_ADMINISTRATOR . '/logs/' . $basename;
        if (!file_exists($file)) return 'Rollback-log ontbreekt: ' . $basename;

        $json = json_decode(file_get_contents($file), true);
        if (!$json) return 'Rollback-log is ongeldig JSON: ' . $basename;

        $db = Factory::getDbo();

        // Delete created (reverse order)
        $deletedCats = 0; $deletedTags = 0;
        foreach (array_reverse($json['created']['categories'] ?? []) as $id) {
            $db->setQuery($db->getQuery(true)->delete('#__categories')->where('id=' . (int)$id))->execute();
            $deletedCats++;
        }
        foreach (array_reverse($json['created']['tags'] ?? []) as $id) {
            $db->setQuery($db->getQuery(true)->delete('#__tags')->where('id=' . (int)$id))->execute();
            $deletedTags++;
        }

        // Revert updated fields back to previous value (which was empty/[]/{})
        $revertedCats = 0; $revertedTags = 0;

        foreach ($json['updated']['categories'] ?? [] as $u) {
            $obj = (object) ['id' => (int)$u['id']];
            foreach (($u['fields'] ?? []) as $field => $prev) {
                $obj->$field = $prev;
            }
            $db->updateObject('#__categories', $obj, 'id');
            $revertedCats++;
        }

        foreach ($json['updated']['tags'] ?? [] as $u) {
            $obj = (object) ['id' => (int)$u['id']];
            foreach (($u['fields'] ?? []) as $field => $prev) {
                $obj->$field = $prev;
            }
            $db->updateObject('#__tags', $obj, 'id');
            $revertedTags++;
        }

        // Clear pointer
        @unlink($cacheFile);

        return 'Rollback uitgevoerd: verwijderd categorieën=' . $deletedCats . ', tags=' . $deletedTags
            . ', teruggedraaid categorieën=' . $revertedCats . ', tags=' . $revertedTags . '.';
    }
}
