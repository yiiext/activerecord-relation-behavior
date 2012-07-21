# ActiveRecord Relation Behavior [![Build Status](https://secure.travis-ci.org/yiiext/activerecord-relation-behavior.png)](http://travis-ci.org/yiiext/activerecord-relation-behavior)

Это расширение вдохновлено всеми расширениями, которые ставят своей целью улучшить сохранение реляционных записей.
Оно позволяет присваивать реляционные записи главным образом для MANY_MANY реляций проще.
Оно соединяет вместе преимущества всех расширений, упомянутых ниже (смотрите заголовок "Сравнение возможностей").
Расширение покрыто на 100% модульными тестами, имеет хорошо структурированный и чистый код, и потому, оно может быть
безопасно и удобно использовано enterprise разработке.

## Требования

* Yii версии 1.1.6 или выше.
* Как и сам Yii Framework данное расширение совместимо со всеми версиями PHP выше 5.1.0.
  Я стараюсь поддерживать те же версии, что и Yii (т.е. 5.1.0), если существуют какие-либо проблемы
  с версиями PHP пожалуйста, [сообщите о них](https://github.com/yiiext/activerecord-relation-behavior/issues)!
* Поведение работает только для тех ActiveRecord классов, у которых определён первичный ключ.
  Убедитесь в том, что вы переопределяете метод [primaryKey()](http://www.yiiframework.com/doc/api/1.1/CActiveRecord#primaryKey%28%29-detail)
  тогда, когда ваша таблица не задаёт первичный ключ.
* _Для того, чтобы запускать модульные тесты вам необходим PHP версии_5.3 или выше._


## Установка

1. Получите исходный код расширения одним из следующих способов:
   * [Скачайте](https://github.com/yiiext/activerecord-relation-behavior/tags) последнюю версию и разместите файлы
     в директории `extensions/yiiext/behaviors/activerecord-relation/` в корне вашего приложения
   * Добавьте этот репозиторий как подмодуль в ваш репозиторий выполнив следующую команду:
     `git submodule add https://github.com/yiiext/activerecord-relation-behavior.git extensions/yiiext/behaviors/activerecord-relation`
2. Добавьте следующие строчки в метод `behaviors()` модели, с которой вы хотите использовать данное расширение:

~~~php
<?php
public function behaviors()
{
    return array(
        'activerecord-relation'=>array(
            'class'=>'ext.yiiext.behaviors.activerecord-relation.EActiveRecordRelationBehavior',
        ),
    );
}
~~~


## Да свершится чудо...

Имеется два ActiveRecord класса (они объявлены [в руководстве Yii](http://www.yiiframework.com/doc/guide/1.1/ru/database.arr#sec-2)):
```php
<?php
class Post extends CActiveRecord
{
    // ...
    public function relations()
    {
        return array(
            'author'     => array(self::BELONGS_TO, 'User',     'author_id'),
            'categories' => array(self::MANY_MANY,  'Category', 'tbl_post_category(post_id, category_id)'),
        );
    }
}

class User extends CActiveRecord
{
    // ...
    public function relations()
    {
        return array(
            'posts'   => array(self::HAS_MANY, 'Post',    'author_id'),
            'profile' => array(self::HAS_ONE,  'Profile', 'owner_id'),
        );
    }
}
```

Где-нибудь в коде нашего приложения мы можем сделать следующие действия:
```php
<?php
    $user = new User();
    $user->posts = array(1,2,3);
    $user->save();
    // пользователь теперь является автором постов 1, 2 и 3

    // следующее эквивалентно предыдушему примеру:
    $user = new User();
    $user->posts = Post::model()->findAllByPk(array(1,2,3));
    $user->save();
    // пользователь теперь является автором постов 1, 2 и 3

    $user->posts = array_merge($user->posts, array(4));
    $user->save();
    // user is now also author of post 4
    // пользователь теперь автор и поста 4

    $user->posts = array();
    $user->save();
    // пользователь теперь не соотнесён ни с одним из постов

    $post = Post::model()->findByPk(2);
    $post->author = User::model()->findByPk(1);
    $post->categories = array(2, Category::model()->findByPk(5));
    $post->save();
    // пост 2 теперь имеет автора 1 и принадлежит к категориям 1 и 5

    // добавление профиля пользователю:
    $user->profile = new Profile();
    $user->profile->save(); // необходимо для того, чтобы точно быть уверенным в том, что профиль имеет первичный ключ
    $user->save();
```


## Моменты, которые необходимо помнить...

* если вы используете данное поведение, то вы уже не можете устанавливать явно атрибуты с внешними ключами.
  Например если вы установите `$model->author_id`, то вы не добьетесь ничего, потому как ARRelationBehavior
  перезапишет его значение null если нет реляционных записей или они не были установлены.
  Вместо этого просто присваивайте значение самой реляции: `$model->author = 1;` / `$model->author = null;`
* Реляции не будут обновлены после сохранения, потому если вы просто установили первичные ключи, то объектов
  там ещё нет. Вызывайте `$model->reload()` для форсирования перезагрузки реляционных записей. Или загружайте
  реляционные записи форсируя так: `$model->getRelated('relationName',true)`.
* Данное поведение работает только с реляциями, которые не имеют дополнительных условий, JOINов, группировок,
  или LIKE т.к. ожидаемый результат после их установки и сохранения не всегда однозначно ясен.
* Если вы присваиваете запись к BELONGS_TO реляции, например `$post->author = $user;`, тогда `$user->posts`
  не будет обновлено автоматически (возможно данный функционал будет добавлен позже).

## Описание исключений

### "You can not save a record that has new related records!"

Вы присвоили реляции такую запись, которая ещё не была сохранена (она ещё не в базе данных).
Т.к. ActiveRecord Relation Behavior нуждается в первичном ключе для сохранения в реляционнй таблице, то это
не сработает. Вам нужно вызвать `->save()` на всех ваших записях перед сохранением в реляции.

### "A HAS_MANY/MANY_MANY relation needs to be an array of records or primary keys!"

Для реляций типа HAS_MANY или MANY_MANY нужно присваивать только массивы. Присваивание одиночных записей
(не массивов) к *_MANY реляциям невозможно.

### "Related record with primary key "X" does not exist!"

Вы попытались присвоить значение первичного ключа _X_ к реляции, но _X_ не существует в базе данных.


## Сравнение возможностей

Вдохновлено и смешаны все возможности следующих Yii расширений:

- может сохранять MANY_MANY реляции, как и cadvancedarbehavior, eadvancedarbehavior, esaverelatedbehavior и advancedrelationsbehavior
- cares about relations when records get deleted like eadvancedarbehavior (not yet implemented, see github [issue #7](https://github.com/yiiext/activerecord-relation-behavior/issues/7))
- может сохранять реляции типов BELONGS_TO, HAS_MANY, HAS_ONE как и eadvancedarbehavior, esaverelatedbehavior и advancedrelationsbehavior
- сохраняет с использованием транзакций и может работать с внешними транзакциями как и with-related-behavior, esaverelatedbehavior и saverbehavior
- не трогает дополнительные данные в таблице связи MANY_MANY (cadvancedarbehavior удаляет их)
- проверяет на массив в реляциях типа HAS_MANY и MANY_MANY чтобы была более понятная семантика

Расширения, упомянутые выше:
- cadvancedarbehavior        http://www.yiiframework.com/extension/cadvancedarbehavior/
- eadvancedarbehavior        http://www.yiiframework.com/extension/eadvancedarbehavior
- advancedrelationsbehavior  http://www.yiiframework.com/extension/advancedrelationsbehavior
- saverbehavior              http://www.yiiframework.com/extension/saverbehavior
- with-related-behavior      https://github.com/yiiext/with-related-behavior
- CSaveRelationsBehavior     http://code.google.com/p/yii-save-relations-ar-behavior/
- esaverelatedbehavior       http://www.yiiframework.com/extension/esaverelatedbehavior

Были изучены, но ничего почерпнутно не было:
- xrelationbehavior          http://www.yiiframework.com/extension/xrelationbehavior
- save-relations-ar-behavior http://www.yiiframework.com/extension/save-relations-ar-behavior

Большое спасибо авторам всех этих расширений за вдохновение и идеи.


## Запуск модульных тестов

Данное поведение покрыто на 100% модульными тестами (ECompositeDbCriteria покрыто не полностью т.к. композитные первичные ключи поддерживаются не полностью).
Для запуска модульных тестов вам нужен установленный [phpunit](https://github.com/sebastianbergmann/phpunit#readme),
тестовые классы требуют PHP 5.3 или выше.

1. Убедитесль, что Yii Framework доступен по пути ./yii/framework
   Вы можете сделать это:
   - клонируя Git репозиторий при помощи `git clone https://github.com/yiisoft/yii.git yii`
   - или создав символическую ссылку на уже существующую директорию с Yii `ln -s ../../path/to/yii yii`
2. Выполните `phpunit EActiveRecordRelationBehaviorTest.php` или если вы хотите информацию о покрытии кода в HTML,
   Выполните `phpunit --coverage-html tmp/coverage EActiveRecordRelationBehaviorTest.php`

## ЧаВО

### When using a MANY_MANY relation, not changing it in any way and doing save() does it re-save relations or not?

It uses `CActiveRecord::hasRelated()` to check if a relation has been
loaded or set and will only save if this is the case.
It will re-save if you loaded and did not change, since it is not able
to detect this.
But re-saving does not mean entries in MANY_MANY table get deleted and
re-inserted. It will only run a delete query, that does not match any rows if you
did not touch records, so no row in db will be touched.

### is it possible to save only related links (n-m table records) without re-saving model?

Currently not, will add this feature in the future: [issue #16](https://github.com/yiiext/activerecord-relation-behavior/issues/16).

### Как я могу удалить определенный ID из MANY_MANY реляции? Мне нужно удалять все реляционные записи для этого?

Сейчас вы должны загрузить все и перезначначить массив. API для этого будет добавлено;
[проблема #16](https://github.com/yiiext/activerecord-relation-behavior/issues/16).
