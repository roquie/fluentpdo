<?php

use PHPUnit\Framework\TestCase;
use Envms\FluentPDO\Query;

class QueryTest extends TestCase {

    protected $fluent;
    
    public function setUp()
    {
        $pdo = new PDO("mysql:dbname=fluentdb;host=localhost", "vagrant","vagrant");

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        
        $this->fluent = new Query($pdo);
    }

    public function testBasicQuery()
    {
        $query = $this->fluent
            ->from('user')
            ->where('id > ?', 0)
            ->orderBy('name');

        $query = $query->where('name = ?', 'Marek');

        self::assertEquals('SELECT user.* FROM user WHERE id > ? AND name = ? ORDER BY name', $query->getQuery(false));
        self::assertEquals(['id' => '1', 'country_id'=> '1' , 'type' => 'admin' ,'name' => 'Marek'] , $query->fetch());
        self::assertEquals([0 => 0, 1 => 'Marek'], $query->getParameters());
    }

    public function testReturnQueryWithHaving()
    {

        $query = $this->fluent
            ->from('user')
            ->select(null)
            ->select('type, count(id) AS type_count')
            ->where('id > ?', 1)
            ->groupBy('type')
            ->having('type_count > ?', 1)
            ->orderBy('name');

        self::assertEquals("SELECT type, count(id) AS type_count FROM user WHERE id > ? GROUP BY type HAVING type_count > ? ORDER BY name", $query->getQuery(false));
    }

    public function testReturnParameterWithId()
    {
        $query = $this->fluent
            ->from('user', 2);

        self::assertEquals([0=> 2], $query->getParameters());
        self::assertEquals('SELECT user.* FROM user WHERE user.id = ?', $query->getQuery(false));
    }

    public function testWhereArrayParameter()
    {
        $query = $this->fluent
            ->from('user')
            ->where(array(
                'id'=> 2,
                'type' => 'author'
            ));

        self::assertEquals([ 0 => 2, 1 => 'author'], $query->getParameters());
        self::assertEquals('SELECT user.* FROM user WHERE id = ? AND type = ?', $query->getQuery(false));
    }

    public function testWhereColumnValue()
    {
        $query = $this->fluent->from('user')
            ->where('type', 'author');

       self::assertEquals([0 => 'author'], $query->getParameters());
       self::assertEquals('SELECT user.* FROM user WHERE type = ?', $query->getQuery(false));
    }

    public function testWhereColumnNull()
    {
        $query = $this->fluent
            ->from('user')
            ->where('type', null);

        self::assertEquals('SELECT user.* FROM user WHERE type is NULL', $query->getQuery(false));
    }

    public function testWhereColumnArray()
    {
        $query = $this->fluent
            ->from('user')
            ->where('id', array(1,2,3));

        self::assertEquals('SELECT user.* FROM user WHERE id IN (1, 2, 3)', $query->getQuery(false));
        self::assertEquals([], $query->getParameters());
    }

    public function testWhereColumnName()
    {
        $query = $this->fluent->from('user')
            ->where('type = :type', array(':type' => 'author'))
            ->where('id > :id AND name <> :name', array(':id' => 1, ':name' => 'Marek'));

        $returnValue = '';
        foreach ($query as $row) {
            $returnValue  = $row['name'];
        }

        self::assertEquals('SELECT user.* FROM user WHERE type = :type AND id > :id AND name <> :name', $query->getQuery(false));
        self::assertEquals([':type' => 'author', ':id' => 1 ,':name' => 'Marek'], $query->getParameters());
        self::assertEquals('Robert', $returnValue);
    }

    public function testFullJoin()
    {
        $query = $this->fluent->from('article')
            ->select('user.name')
            ->leftJoin('user ON user.id = article.user_id')
            ->orderBy('article.title');

        $returnValue = '';
        foreach ($query as $row) {
            $returnValue .= "$row[name] - $row[title] ";
        }

        self::assertEquals('SELECT article.*, user.name FROM article LEFT JOIN user ON user.id = article.user_id ORDER BY article.title', $query->getQuery(false));
        self::assertEquals('Marek - article 1 Robert - article 2 Marek - article 3 ', $returnValue);
    }

    public function testShortJoin()
    {

        $query = $this->fluent->from('article')->leftJoin('user');
        $query2 = $this->fluent->from('article')->leftJoin('user author');
        $query3 = $this->fluent->from('article')->leftJoin('user AS author');

        self::assertEquals('SELECT article.* FROM article LEFT JOIN user ON user.id = article.user_id', $query->getQuery(false));
        self::assertEquals('SELECT article.* FROM article LEFT JOIN user AS author ON author.id = article.user_id', $query2->getQuery(false));
        self::assertEquals('SELECT article.* FROM article LEFT JOIN user AS author ON author.id = article.user_id', $query3->getQuery(false));
    }

    public function testJoinShortBackRef()
    {
        $query = $this->fluent->from('user')->innerJoin('article:');
        $query2 = $this->fluent->from('user')->innerJoin('article: with_articles');
        $query3 = $this->fluent->from('user')->innerJoin('article: AS with_articles');

        self::assertEquals('SELECT user.* FROM user INNER JOIN article ON article.user_id = user.id', $query->getQuery(false));
        self::assertEquals('SELECT user.* FROM user INNER JOIN article AS with_articles ON with_articles.user_id = user.id', $query2->getQuery(false));
        self::assertEquals('SELECT user.* FROM user INNER JOIN article AS with_articles ON with_articles.user_id = user.id', $query3->getQuery(false));
    }

    public function testJoinShortMulti()
    {
        $query = $this->fluent->from('comment')
            ->leftJoin('article.user');

        self::assertEquals('SELECT comment.* FROM comment LEFT JOIN article ON article.id = comment.article_id  LEFT JOIN user ON user.id = article.user_id', $query->getQuery(false));
    }

    public function testJoinMultiBackRef()
    {
        $query = $this->fluent->from('article')
            ->innerJoin('comment:user AS comment_user');

        self::assertEquals('SELECT article.* FROM article INNER JOIN comment ON comment.article_id = article.id  INNER JOIN user AS comment_user ON comment_user.id = comment.user_id', $query->getQuery(false));
        self::assertEquals(['id' => 1, 'user_id' => 1, 'published_at' => '2011-12-10 12:10:00', 'title' => 'article 1', 'content' => 'content 1'] , $query->fetch());
    }

    public function testJoinShortTwoSameTable()
    {
        $query = $this->fluent->from('article')
            ->leftJoin('user')
            ->leftJoin('user');

        self::assertEquals('SELECT article.* FROM article LEFT JOIN user ON user.id = article.user_id', $query->getQuery(false));
    }

    public function testJoinShortTwoTables()
    {
        $query = $this->fluent->from('comment')
            ->where('comment.id', 1)
            ->leftJoin('user comment_author')->select('comment_author.name AS comment_name')
            ->leftJoin('article.user AS article_author')->select('article_author.name AS author_name');

        self::assertEquals('SELECT comment.*, comment_author.name AS comment_name, article_author.name AS author_name FROM comment LEFT JOIN user AS comment_author ON comment_author.id = comment.user_id  LEFT JOIN article ON article.id = comment.article_id  LEFT JOIN user AS article_author ON article_author.id = article.user_id WHERE comment.id = ?', $query->getQuery(false) );
        self::assertEquals(['id' => '1', 'article_id' => '1', 'user_id' => '2', 'content' => 'comment 1.1', 'comment_name' => 'Robert', 'author_name' => 'Marek'], $query->fetch());
    }

    public function testFluentUtil()
    {

        $value  =  Envms\FluentPDO\Utilities::toUpperWords('one');
        $value2 =  Envms\FluentPDO\Utilities::toUpperWords(' one ');
        $value3 =  Envms\FluentPDO\Utilities::toUpperWords('oneTwo');
        $value4 =  Envms\FluentPDO\Utilities::toUpperWords('OneTwo');
        $value5 =  Envms\FluentPDO\Utilities::toUpperWords('oneTwoThree');
        $value6 =  Envms\FluentPDO\Utilities::toUpperWords(' oneTwoThree ');

        self::assertEquals('ONE', $value);
        self::assertEquals('ONE', $value2);
        self::assertEquals('ONE TWO', $value3);
        self::assertEquals('ONE TWO', $value4);
        self::assertEquals('ONE TWO THREE', $value5);
        self::assertEquals('ONE TWO THREE', $value6);

    }

    public function testJoinInWhere()
    {
        $query = $this->fluent->from('article')->where('comment:content <> "" AND user.country.id = ?', 1);

        self::assertEquals('SELECT article.* FROM article LEFT JOIN comment ON comment.article_id = article.id  LEFT JOIN user ON user.id = article.user_id  LEFT JOIN country ON country.id = user.country_id WHERE comment.content <> "" AND country.id = ?', $query->getQuery(false));
    }

    public function testJoinInSelect()
    {
        $query = $this->fluent->from('article')->select('user.name as author');

        self::assertEquals('SELECT article.*, user.name as author FROM article LEFT JOIN user ON user.id = article.user_id', $query->getQuery(false));
    }

    public function testJoinInOrderBy()
    {
        $query = $this->fluent->from('article')->orderBy('user.name, article.title');

        self::assertEquals('SELECT article.* FROM article LEFT JOIN user ON user.id = article.user_id ORDER BY user.name, article.title', $query->getQuery(false));
    }

    public function testJoinInGroupBy()
    {
        $query = $this->fluent->from('article')->groupBy('user.type')
            ->select(null)->select('user.type, count(article.id) as article_count');

        self::assertEquals('SELECT user.type, count(article.id) as article_count FROM article LEFT JOIN user ON user.id = article.user_id GROUP BY user.type', $query->getQuery(false));
        self::assertEquals( ['0' => ['type' => 'admin', 'article_count' => '2'], '1' => ['type' => 'author', 'article_count' => '1']], $query->fetchAll());
    }

    public function testDontCreateDuplicateJoins()
    {
        $query = $this->fluent->from('article')->innerJoin('user AS author ON article.user_id = author.id')
            ->select('author.name');
        $query2 = $this->fluent->from('article')->innerJoin('user ON article.user_id = user.id')
            ->select('user.name');
        $query3 = $this->fluent->from('article')->innerJoin('user AS author ON article.user_id = author.id')
            ->select('author.country.name');
        $query4 = $this->fluent->from('article')->innerJoin('user ON article.user_id = user.id')
            ->select('user.country.name');

        self::assertEquals('SELECT article.*, author.name FROM article INNER JOIN user AS author ON article.user_id = author.id', $query->getQuery(false));
        self::assertEquals('SELECT article.*, user.name FROM article INNER JOIN user ON article.user_id = user.id', $query2->getQuery(false));
        self::assertEquals('SELECT article.*, country.name FROM article INNER JOIN user AS author ON article.user_id = author.id  LEFT JOIN country ON country.id = author.country_id', $query3->getQuery(false));
        self::assertEquals('SELECT article.*, country.name FROM article INNER JOIN user ON article.user_id = user.id  LEFT JOIN country ON country.id = user.country_id', $query4->getQuery(false));
    }

    public function testClauseWithRefBeforeJoin()
    {
        $query = $this->fluent->from('article')->select('user.name')->innerJoin('user');
        $query2 = $this->fluent->from('article')->select('author.name')->innerJoin('user as author');
        $query3 = $this->fluent->from('user')->select('article:title')->innerJoin('article:');

        self::assertEquals('SELECT article.*, user.name FROM article INNER JOIN user ON user.id = article.user_id', $query->getQuery(false));
        self::assertEquals('SELECT article.*, author.name FROM article INNER JOIN user AS author ON author.id = article.user_id', $query2->getQuery(false));
        self::assertEquals('SELECT user.*, article.title FROM user INNER JOIN article ON article.user_id = user.id', $query3->getQuery(false));
    }

    public function testAliasesForClausesGroupbyOrderBy()
    {
        $query = $this->fluent->from('article')->group('user_id')->order('id');

        self::assertEquals('SELECT article.* FROM article GROUP BY user_id ORDER BY id', $query->getQuery(false));
    }

    public function testFetch()
    {
        $queryPrint = $this->fluent->from('user', 1)->fetch('name');
        $queryPrint2 = $this->fluent->from('user', 1)->fetch();
        $statement = $this->fluent->from('user', 3)->fetch();
        $statement2 = $this->fluent->from('user', 3)->fetch('name');

        self::assertEquals('Marek', $queryPrint);
        self::assertEquals(['id' => '1', 'country_id' => '1', 'type' => 'admin', 'name' => 'Marek'], $queryPrint2);
        self::assertEquals(false, $statement);
        self::assertEquals(false, $statement2);
    }

    public function testFetchPairsFetchAll()
    {
        $result = $this->fluent->from('user')->fetchPairs('id', 'name');
        $result2 = $this->fluent->from('user')->fetchAll();

        self::assertEquals(['1' => 'Marek', '2' => 'Robert'], $result);
        self::assertEquals(['0' => ['id' => '1', 'country_id' => '1', 'type' => 'admin', 'name' => 'Marek'], '1' => ['id' => '2', 'country_id' => '1', 'type' => 'author', 'name' => 'Robert']], $result2);
    }

    public function testFetchAllWithParams()
    {
        $result = $this->fluent->from('user')->fetchAll('id', 'type, name');

        self::assertEquals(['1' => ['id' => '1', 'type' => 'admin', 'name' => 'Marek'], '2' => ['id' => '2', 'type' => 'author', 'name' => 'Robert']], $result);
    }

    public function testFromOtherDB() {
        $queryPrint = $this->fluent->from('db2.user')->order('db2.user.name')->getQuery(false);

        self::assertEquals('SELECT db2.user.* FROM db2.user ORDER BY db2.user.name', $queryPrint);
    }

    public function testJoinTableWithUsing()
    {
        $query = $this->fluent->from('article')
                ->innerJoin('user USING (user_id)')
                ->select('user.*')
                ->getQuery(false);

        $query2 = $this->fluent->from('article')
                ->innerJoin('user u USING (user_id)')
                ->select('u.*')
                ->getQuery(false);

        $query3 = $this->fluent->from('article')
                ->innerJoin('user AS u USING (user_id)')
                ->select('u.*')
                ->getQuery(false);

        self::assertEquals('SELECT article.*, user.* FROM article INNER JOIN user USING (user_id)', $query);
        self::assertEquals('SELECT article.*, u.* FROM article INNER JOIN user u USING (user_id)', $query2);
        self::assertEquals('SELECT article.*, u.* FROM article INNER JOIN user AS u USING (user_id)', $query3);
    }

    public function testFromWithAlias()
    {
        $query = $this->fluent->from('user author')->getQuery(false);
        $query2 = $this->fluent->from('user AS author')->getQuery(false);
        $query3 = $this->fluent->from('user AS author', 1)->getQuery(false);
        $query4 = $this->fluent->from('user AS author')->select('country.name')->getQuery(false);

        self::assertEquals('SELECT author.* FROM user author', $query);
        self::assertEquals('SELECT author.* FROM user AS author', $query2);
        self::assertEquals('SELECT author.* FROM user AS author WHERE author.id = ?', $query3);
        self::assertEquals('SELECT author.*, country.name FROM user AS author LEFT JOIN country ON country.id = user AS author.country_id', $query4);
    }

    public function testInsertStatement()
    {
        $query = $this->fluent->insertInto('article', array(
                'user_id' => 1,
                'title' => 'new title',
                'content' => 'new content'
            ));

     //   $executeReturn = $pdo->query('DELETE FROM article WHERE id > 3')->execute();

        self::assertEquals('INSERT INTO article (user_id, title, content) VALUES (?, ?, ?)', $query->getQuery(false));
        self::assertEquals(['0' => '1', '1' => 'new title', '2' => 'new content'], $query->getParameters());
        //  self::assertEquals('Array([0] => 1, [1] => new title, [2] => new content', $query->getParameters());
    }

 /*   public function testInsertUpdate()
    {
        $query = $this->fluent->insertInto('article', array('id' => 1))
            ->onDuplicateKeyUpdate(array(
                'title' => 'article 1b',
                'content' => new Envms\FluentPDO\Literal('abs(-1)') // let's update with a literal and a parameter value
            ));

        $q = $this->fluent->from('article', 1)->fetch();

        $query2 = $this->fluent->insertInto('article', array('id' => 1))
            ->onDuplicateKeyUpdate(array(
                'title' => 'article 1',
                'content' => 'content 1',
            ))->execute();

        $q2 = $this->fluent->from('article', 1)->fetch();

        $parameters = print_r($query->getParameters());
        $insertStatement = 'last_inserted_id = ' . $query->execute();
        $printParameters = print_r($q);
        $insertStatement2 = "last_inserted_id =". $query2;
        $printParameters2 = print_r($q2);

        self::assertEquals('INSERT INTO article (id) VALUES (?) ON DUPLICATE KEY UPDATE title = ?, content = abs(-1)', $query->getQuery(false));
       // self::assertEquals('Array([0] => 1,[1] => article 1b)', $parameters);
      //  self::assertEquals('last_inserted_id = 1', $insertStatement);
      //  self::assertEquals('Array([id] => 1,[user_id] => 1,[published_at] => 2011-12-10 12:10:00,[title] => article 1b,[content] => 1)', $printParameters);
      //  self::assertEquals('last_inserted_id = 1', $insertStatement2);
      //  self::assertEquals('Array([id] => 1,[user_id] => 1,[published_at] => 2011-12-10 12:10:00,[title] => article 1,[content] => content 1)', $printParameters2);
    }*/

     public function testInsertIgnore()
     {
        $query = $this->fluent->insertInto('article',
            array(
                'user_id' => 1,
                'title' => 'new title',
                'content' => 'new content',
            ))->ignore();

        self::assertEquals('INSERT IGNORE INTO article (user_id, title, content) VALUES (?, ?, ?)', $query->getQuery(false));
        self::assertEquals(['0' => '1', '1' => 'new title', '2' => 'new content'], $query->getParameters());
    }

    public function testInsertWithLiteral()
    {
        $query = $this->fluent->insertInto('article',
            array(
                'user_id' => 1,
                'updated_at' => new Envms\FluentPDO\Literal('NOW()'),
                'title' => 'new title',
                'content' => 'new content',
            ));

        self::assertEquals('INSERT INTO article (user_id, updated_at, title, content) VALUES (?, NOW(), ?, ?)', $query->getQuery(false));
        self::assertEquals(['0' => '1','1' => 'new title', '2' => 'new content'], $query->getParameters());
    }

    public function testDisableSmartJoin()
    {
        $query = $this->fluent->from('comment')
            ->select('user.name')
            ->orderBy('article.published_at')
            ->getQuery(false);
        $printQuery = "-- Plain: $query";

        $query2 = $this->fluent->from('comment')
            ->select('user.name')
            ->disableSmartJoin()
            ->orderBy('article.published_at')
            ->getQuery(false);

        $printQuery2 = "-- Disable: $query2";

        $query3 = $this->fluent->from('comment')
            ->disableSmartJoin()
            ->select('user.name')
            ->enableSmartJoin()
            ->orderBy('article.published_at')
            ->getQuery(false);
        $printQuery3 = "-- Disable and enable: $query3";

        self::assertEquals('-- Plain: SELECT comment.*, user.name FROM comment LEFT JOIN user ON user.id = comment.user_id  LEFT JOIN article ON article.id = comment.article_id ORDER BY article.published_at', $printQuery);
        self::assertEquals('-- Disable: SELECT comment.*, user.name FROM comment ORDER BY article.published_at', $printQuery2);
        self::assertEquals('-- Disable and enable: SELECT comment.*, user.name FROM comment LEFT JOIN user ON user.id = comment.user_id  LEFT JOIN article ON article.id = comment.article_id ORDER BY article.published_at', $printQuery3);
    }

    public function testFetchColumn()
    {
        $printColumn = $this->fluent->from('user', 1)->fetchColumn();
        $printColumn2 = $this->fluent->from('user', 1)->fetchColumn(3);
        $statement = $this->fluent->from('user', 3)->fetchColumn();
        $statement2 = $this->fluent->from('user', 3)->fetchColumn(3);

        self::assertEquals(1, $printColumn);
        self::assertEquals('Marek', $printColumn2);
        self::assertEquals(false, $statement);
        self::assertEquals(false, $statement2);
    }

    public function testPDOFetchObj()
    {
        $query = $this->fluent->from('user')->where('id > ?', 0)->orderBy('name');
        $query = $query->where('name = ?', 'Marek');
        $this->fluent->getPdo()->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        self::assertEquals(['0' => '0', '1' => 'Marek'], $query->getParameters());

       // self::assertEquals(['id' => '1', 'country_id' => '1', 'type' => 'admin' , 'name' => 'Marek'}, $query->fetch()];
    }

/*    public function testUpdate()
    {
        $query = $this->fluent->update('country')->set('name', 'aikavolS')->where('id', 1);
        $query->execute();

        $query2 = $this->fluent->from('country')->where('id', 1);

        $this->fluent->update('country')->set('name', 'Slovakia')->where('id', 1)->execute();

        $query3 = $this->fluent->from('country')->where('id', 1);


        self::assertEquals('UPDATE country SET name = ? WHERE id = ?', $query->getQuery(false));
      //  self::assertEquals(['0' => 'aikavolS','1' => '1'], $query->getParameters());
      //  self::assertEquals(['id' => '1', 'name' => 'Slovakia'], $query2->fetch());
      //  self::assertEquals(['id' => '1', 'name' => 'Slovakia'], $query3->fetch());
    }*/

    public function testUpdateLiteral()
    {
        $query = $this->fluent->update('article')->set('published_at', new Envms\FluentPDO\Literal('NOW()'))->where('user_id', 1);

        self::assertEquals('UPDATE article SET published_at = NOW() WHERE user_id = ?',  $query->getQuery(false));
        self::assertEquals(['0' => '1'], $query->getParameters());
    }

    public function testUpdateFromArray()
    {
        $query = $this->fluent->update('user')->set(array('name' => 'keraM', '`type`' => 'author'))->where('id', 1);
        $query->execute();

        self::assertEquals('UPDATE user SET name = ?, `type` = ? WHERE id = ?', $query->getQuery(false));
        self::assertEquals(['0' => 'keraM', '1' => 'author', '2' => '1'], $query->getParameters());
    }

    public function testUpdateLeftJoin()
    {
        $query = $this->fluent->update('user')
            ->outerJoin('country ON country.id = user.country_id')
            ->set(array('name' => 'keraM', '`type`' => 'author'))
            ->where('id', 1);

        self::assertEquals('UPDATE user OUTER JOIN country ON country.id = user.country_id SET name = ?, `type` = ? WHERE id = ?', $query->getQuery(false));
        self::assertEquals(['0' => 'keraM' , '1' => 'author', '2' => '1'], $query->getParameters());
    }

    public function testUpdateSmartJoin()
    {
        $query = $this->fluent->update('user')
            ->set(array('type' => 'author'))
            ->where('country.id', 1);

        self::assertEquals('UPDATE user LEFT JOIN country ON country.id = user.country_id SET type = ? WHERE country.id = ?', $query->getQuery(false));
        self::assertEquals(['0' => 'author', '1' => '1'], $query->getParameters());
    }

    public function testUpdateOrderLimit()
    {
        $query = $this->fluent->update('user')
            ->set(array('type' => 'author'))
            ->where('id', 2)
            ->orderBy('name')
            ->limit(1);

        self::assertEquals('UPDATE user SET type = ? WHERE id = ? ORDER BY name LIMIT 1',  $query->getQuery(false));
        self::assertEquals(['0' => 'author' ,'1' => '2'], $query->getParameters());
    }

    public function testDelete()
    {
        $query = $this->fluent->deleteFrom('user')
            ->where('id', 1);

        self::assertEquals('DELETE FROM user WHERE id = ?',  $query->getQuery(false));
        self::assertEquals(['0' => '1'], $query->getParameters());
    }

    public function testDeleteIgnore()
    {
        $query = $this->fluent->deleteFrom('user')
            ->ignore()
            ->where('id', 1);

        self::assertEquals('DELETE IGNORE FROM user WHERE id = ?', $query->getQuery(false));
        self::assertEquals(['0' => '1'], $query->getParameters());
    }

    public function testDeleteOrderLimit()
    {
        $query = $this->fluent->deleteFrom('user')
            ->where('id', 2)
            ->orderBy('name')
            ->limit(1);

        self::assertEquals('DELETE FROM user WHERE id = ? ORDER BY name LIMIT 1', $query->getQuery(false));
        self::assertEquals(['0' => '2'], $query->getParameters());
    }

    public function testDeleteExpanded()
    {
        $query = $this->fluent->delete('t1, t2')
            ->from('t1')
            ->innerJoin('t2 ON t1.id = t2.id')
            ->innerJoin('t3 ON t2.id = t3.id')
            ->where('t1.id', 1);

        self::assertEquals('DELETE t1, t2 FROM t1 INNER JOIN t2 ON t1.id = t2.id INNER JOIN t3 ON t2.id = t3.id WHERE t1.id = ?', $query->getQuery(false));
        self::assertEquals(['0' => '1'], $query->getParameters());
    }

    public function testUpdateShortCut()
    {
        $query = $this->fluent->update('user', array('type' => 'admin'), 1);

        self::assertEquals('UPDATE user SET type = ? WHERE id = ?', $query->getQuery(false));
        self::assertEquals(['0' => 'admin', '1' => '1'], $query->getParameters());
    }

    public function testDeleteShortcut()
    {
        $query = $this->fluent->deleteFrom('user', 1);

        self::assertEquals('DELETE FROM user WHERE id = ?', $query->getQuery(false));
        self::assertEquals(['0' => '1'], $query->getParameters());
    }

    public function testAddFromAfterDelete()
    {
        $query = $this->fluent->delete('user', 1)->from('user');

        self::assertEquals('DELETE user FROM user WHERE id = ?', $query->getQuery(false));
        self::assertEquals(['0' => '1'], $query->getParameters());
    }

    public function testFromIdAsObject()
    {
        $query = $this->fluent->from('user', 2)->asObject();

        self::assertEquals('SELECT user.* FROM user WHERE user.id = ?', $query->getQuery(false));
     //   self::assertEquals('stdClass Object([id] => 2,[country_id] => 1,[type] => author,[name] => Robert)', $query->fetch());
    }

/*    public function testFromIdAsObjectUser()
    {
        class User { public $id, $country_id, $type, $name; }
        $query = $this->fluent->from('user', 2)->asObject('User');

        $printQuery = $query->getQuery();
        $parameters = print_r($query->fetch());

        self::assertEquals('SELECT user.* FROM user WHERE user.id = ?', $printQuery);
        self::assertEquals('User Object([id] => 2,[country_id] => 1,[type] => author,[name] => Robert)', $parameters);
    }*/

    public function testWhereReset()
    {
        $query = $this->fluent->from('user')->where('id > ?', 0)->orderBy('name');
        $query = $query->where(null)->where('name = ?', 'Marek');

        self::assertEquals('SELECT user.* FROM user WHERE name = ? ORDER BY name', $query->getQuery(false));
        self::assertEquals('Array([0] => Marek)', $query->getParameters());
        self::assertEquals(['id' => '1','country_id' => '1','type' => 'admin','name' => 'Marek'], $query->fetch());
    }

    public function testUpdateZero()
    {
        $this->fluent->update('article')->set('content', '')->where('id', 1)->execute();
        $user = $this->fluent->from('article')->where('id', 1)->fetch();

        $printQuery = 'ID: ' . $user['id'] . ' - content: ' . $user['content'] ;

        $this->fluent->update('article')->set('content', 'content 1')->where('id', 1)->execute();

        $user2 = $this->fluent->from('article')->where('id', 1)->fetch();

        $printQuery2 = 'ID: ' . $user2['id'] . ' - content: ' . $user2['content'];

        self::assertEquals('ID: 1 - content:', $printQuery);
        self::assertEquals('ID: 1 - content: content 1', $printQuery2);
    }

    public function testSelectArrayParam()
    {
        $query = $this->fluent
            ->from('user')
            ->select(null)
            ->select(array('id', 'name'))
            ->where('id < ?', 2);

        self::assertEquals('SELECT id, name FROM user WHERE id < ?', $query->getQuery(false));
        self::assertEquals(['0' => '2'], $query->getParameters());
        self::assertEquals(['id' => '1', 'name' => 'Marek'], $query->fetch());
    }

    public function testGroupByArrayParam()
    {
        $query = $this->fluent
            ->from('user')
            ->select(null)
            ->select('count(*) AS total_count')
            ->groupBy(array('id', 'name'));

        self::assertEquals('SELECT count(*) AS total_count FROM user GROUP BY id,name', $query->getQuery(false));
        self::assertEquals(['total_count' => '1'], $query->fetch());
    }

    public function testCountable()
    {
        $articles = $this->fluent
            ->from('article')
            ->select(NULL)
            ->select('title')
            ->where('id > 1');

        $count = count($articles);

        self::assertEquals(2, $count);
        self::assertEquals(['0' => ['title' => 'article 2'], '1' =>['title' => 'article 3']], $articles->fetchAll());
    }

    public function testWhereNotArray()
    {
        $query = $this->fluent->from('article')->where('NOT id', array(1,2));

        self::assertEquals('SELECT article.* FROM article WHERE NOT id IN (1, 2)',  $query->getQuery(false));
    }

    public function testWhereColNameEscaped()
    {
        $query = $this->fluent->from('user')
            ->where('`type` = :type', array(':type' => 'author'))
            ->where('`id` > :id AND `name` <> :name', array(':id' => 1, ':name' => 'Marek'));

        $rowDisplay = '';
        foreach ($query as $row) {
            $rowDisplay = $row['name'];
        }

        self::assertEquals('SELECT user.* FROM user WHERE `type` = :type AND `id` > :id AND `name` <> :name', $query->getQuery(false));
        self::assertEquals([':type' => 'author', ':id' => '1', ':name' => 'Marek'], $query->getParameters());
        self::assertEquals('Robert', $rowDisplay);
    }

    public function testUpdateWhere()
    {
        $query = $this->fluent->update('users')
            ->set("`users`.`active`", 1)
            ->where("`country`.`name`", 'Slovakia')
            ->where("`users`.`name`", 'Marek');

        $query2 = $this->fluent->update('users')
            ->set("[users].[active]", 1)
            ->where("[country].[name]", 'Slovakia')
            ->where("[users].[name]", 'Marek');

        self::assertEquals('UPDATE users LEFT JOIN country ON country.id = users.country_id SET `users`.`active` = ? WHERE `country`.`name` = ? AND `users`.`name` = ?', $query->getQuery(false));
        self::assertEquals(['0' => '1','1' => 'Slovakia','2' => 'Marek'], $query->getParameters());
        self::assertEquals('UPDATE users LEFT JOIN country ON country.id = users.country_id SET [users].[active] = ? WHERE [country].[name] = ? AND [users].[name] = ?', $query2->getQuery(false));
        self::assertEquals(['0' => '1', '1' => 'Slovakia', '2' => 'Marek'], $query2->getParameters());
    }
}