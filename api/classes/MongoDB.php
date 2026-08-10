<?php
/**
 * ACSESS - MongoDB Adapter
 * PHP native MongoDB Driver (MongoDB\Driver\*) asosida yaratilgan universal database klassi.
 */

class MongoDBDatabase {
    private $manager;
    private $dbName;

    public function __construct($uri = null, $dbName = null) {
        $uri = $uri ?: (defined('MONGODB_URI') ? MONGODB_URI : 'mongodb://127.0.0.1:27017');
        $this->dbName = $dbName ?: (defined('DB_NAME') ? DB_NAME : 'acsess4');
        $this->manager = new MongoDB\Driver\Manager($uri);
    }

    public function getManager() {
        return $this->manager;
    }

    public function getDatabaseName() {
        return $this->dbName;
    }

    /**
     * ObjectId ga xavfsiz o'girish
     */
    public function toObjectId($id) {
        if ($id instanceof MongoDB\BSON\ObjectId) {
            return $id;
        }
        if (is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id)) {
            try {
                return new MongoDB\BSON\ObjectId($id);
            } catch (Throwable $e) {
                return $id;
            }
        }
        return $id;
    }

    /**
     * Filterni normallashtirish: 'id' maydoni bo'lsa, uni '_id' yoki ikkalasiga moslashtiradi
     */
    public function normalizeFilter($filter) {
        if (!is_array($filter)) {
            return [];
        }

        $normalized = [];
        foreach ($filter as $k => $v) {
            if ($k === 'id') {
                $objId = $this->toObjectId($v);
                if ($objId instanceof MongoDB\BSON\ObjectId) {
                    $normalized['$or'] = [
                        ['_id' => $objId],
                        ['id' => $v]
                    ];
                } else {
                    $normalized['$or'] = [
                        ['_id' => $v],
                        ['id' => $v]
                    ];
                }
            } elseif ($k === '_id') {
                $normalized['_id'] = $this->toObjectId($v);
            } else {
                $normalized[$k] = $v;
            }
        }
        return $normalized;
    }

    /**
     * Hujjatni PHP massiviga aylantirish va 'id' maydonini ta'minlash
     */
    public function formatDoc($doc) {
        if (!$doc) return null;
        if (is_object($doc)) {
            $doc = (array)$doc;
        }

        $formatted = [];
        foreach ($doc as $key => $val) {
            if ($val instanceof MongoDB\BSON\ObjectId) {
                $formatted[$key] = (string)$val;
            } elseif ($val instanceof MongoDB\BSON\UTCDateTime) {
                $formatted[$key] = $val->toDateTime()->format('Y-m-d H:i:s');
            } elseif ($val instanceof stdClass || is_array($val)) {
                $formatted[$key] = json_decode(json_encode($val), true);
            } else {
                $formatted[$key] = $val;
            }
        }

        if (isset($formatted['_id']) && !isset($formatted['id'])) {
            $formatted['id'] = (string)$formatted['_id'];
        }

        return $formatted;
    }

    /**
     * Hujjatlarni qidirish (SELECT)
     */
    public function find($collection, $filter = [], $options = []) {
        $filter = $this->normalizeFilter($filter);
        $query = new MongoDB\Driver\Query($filter, $options);
        $cursor = $this->manager->executeQuery("{$this->dbName}.{$collection}", $query);
        $results = [];
        foreach ($cursor as $doc) {
            $results[] = $this->formatDoc($doc);
        }
        return $results;
    }

    /**
     * Bitta hujjatni qidirish (SELECT LIMIT 1)
     */
    public function findOne($collection, $filter = [], $options = []) {
        $options['limit'] = 1;
        $results = $this->find($collection, $filter, $options);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Hujjat qo'shish (INSERT ONE)
     */
    public function insertOne($collection, array $document) {
        if (!isset($document['created_at'])) {
            $document['created_at'] = date('Y-m-d H:i:s');
        }

        // Agar _id ko'rsatilmagan bo'lsa, avtomatik yaratiladi
        if (!isset($document['_id'])) {
            $document['_id'] = new MongoDB\BSON\ObjectId();
        }

        $bulk = new MongoDB\Driver\BulkWrite();
        $insertedId = $bulk->insert($document);
        $this->manager->executeBulkWrite("{$this->dbName}.{$collection}", $bulk);

        return (string)$insertedId;
    }

    /**
     * Ko'plab hujjatlarni qo'shish (INSERT MANY)
     */
    public function insertMany($collection, array $documents) {
        if (empty($documents)) return 0;

        $bulk = new MongoDB\Driver\BulkWrite();
        $count = 0;
        foreach ($documents as $doc) {
            if (!isset($doc['created_at'])) {
                $doc['created_at'] = date('Y-m-d H:i:s');
            }
            if (!isset($doc['_id'])) {
                $doc['_id'] = new MongoDB\BSON\ObjectId();
            }
            $bulk->insert($doc);
            $count++;
        }
        $result = $this->manager->executeBulkWrite("{$this->dbName}.{$collection}", $bulk);
        return $result->getInsertedCount() ?: $count;
    }

    /**
     * Hujjatni yangilash (UPDATE ONE)
     */
    public function updateOne($collection, $filter, array $update, $options = []) {
        $filter = $this->normalizeFilter($filter);
        $bulk = new MongoDB\Driver\BulkWrite();

        // Agar $set yoki boshqa $ operator bo'lmasa, uni $set ga o'raymiz
        $hasOperator = false;
        foreach (array_keys($update) as $k) {
            if (strpos((string)$k, '$') === 0) {
                $hasOperator = true;
                break;
            }
        }
        if (!$hasOperator) {
            $update = ['$set' => $update];
        }

        $updateOptions = array_merge(['multi' => false, 'upsert' => false], $options);
        $bulk->update($filter, $update, $updateOptions);

        $result = $this->manager->executeBulkWrite("{$this->dbName}.{$collection}", $bulk);
        return $result->getModifiedCount();
    }

    /**
     * Hujjatlarni yangilash (UPDATE MANY)
     */
    public function updateMany($collection, $filter, array $update, $options = []) {
        $filter = $this->normalizeFilter($filter);
        $bulk = new MongoDB\Driver\BulkWrite();

        $hasOperator = false;
        foreach (array_keys($update) as $k) {
            if (strpos((string)$k, '$') === 0) {
                $hasOperator = true;
                break;
            }
        }
        if (!$hasOperator) {
            $update = ['$set' => $update];
        }

        $updateOptions = array_merge(['multi' => true, 'upsert' => false], $options);
        $bulk->update($filter, $update, $updateOptions);

        $result = $this->manager->executeBulkWrite("{$this->dbName}.{$collection}", $bulk);
        return $result->getModifiedCount();
    }

    /**
     * Upsert qilish (Insert yoki Update)
     */
    public function upsert($collection, $filter, array $update) {
        return $this->updateOne($collection, $filter, $update, ['upsert' => true]);
    }

    /**
     * Hujjatni o'chirish (DELETE ONE)
     */
    public function deleteOne($collection, $filter) {
        $filter = $this->normalizeFilter($filter);
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete($filter, ['limit' => 1]);
        $result = $this->manager->executeBulkWrite("{$this->dbName}.{$collection}", $bulk);
        return $result->getDeletedCount();
    }

    /**
     * Hujjatlarni o'chirish (DELETE MANY)
     */
    public function deleteMany($collection, $filter = []) {
        $filter = $this->normalizeFilter($filter);
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete($filter, ['limit' => 0]);
        $result = $this->manager->executeBulkWrite("{$this->dbName}.{$collection}", $bulk);
        return $result->getDeletedCount();
    }

    /**
     * Hujjatlar sonini sanash (COUNT)
     */
    public function count($collection, $filter = []) {
        $filter = $this->normalizeFilter($filter);
        $command = new MongoDB\Driver\Command([
            'count' => $collection,
            'query' => (object)$filter
        ]);
        $cursor = $this->manager->executeCommand($this->dbName, $command);
        $res = current($cursor->toArray());
        return (int)($res->n ?? 0);
    }

    /**
     * Aggregation Pipeline bajarish
     */
    public function aggregate($collection, array $pipeline) {
        $command = new MongoDB\Driver\Command([
            'aggregate' => $collection,
            'pipeline' => $pipeline,
            'cursor' => new stdClass()
        ]);
        $cursor = $this->manager->executeCommand($this->dbName, $command);
        $results = [];
        foreach ($cursor as $doc) {
            $results[] = $this->formatDoc($doc);
        }
        return $results;
    }

    /**
     * Indeks yaratish
     */
    public function createIndex($collection, array $keys, array $options = []) {
        $indexDoc = array_merge(['key' => $keys, 'name' => implode('_', array_keys($keys)) . '_idx'], $options);
        $command = new MongoDB\Driver\Command([
            'createIndexes' => $collection,
            'indexes' => [$indexDoc]
        ]);
        try {
            $this->manager->executeCommand($this->dbName, $command);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Kolleksiyani tozalash (DROP)
     */
    public function dropCollection($collection) {
        $command = new MongoDB\Driver\Command([
            'drop' => $collection
        ]);
        try {
            $this->manager->executeCommand($this->dbName, $command);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
