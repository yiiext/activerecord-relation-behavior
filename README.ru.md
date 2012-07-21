# Поведение ActiveRecord Relation [![Build Status](https://secure.travis-ci.org/yiiext/activerecord-relation-behavior.png)](http://travis-ci.org/yiiext/activerecord-relation-behavior)

Данное расширение создано на основе идей, почерпнутых из других расширений, которые упрощают сохранение связанных
данных (смотрите раздел «Сравнение возможностей»). Оно, главным образом, упрощает работу с MANY_MANY реляциями.
Расширение на 100% покрыто модульными тестами, имеет правильно структурированный и чистый код, и потому, оно может
без опасений использоваться в приложениях масштаба предприятия.


## Требования

* Yii версии 1.1.6 или выше.
* Как и фреймворк Yii данное расширение совместимо со всеми версиями PHP 5.1.0 или выше. Если у вас возникли проблемы
  с поддержкой определённых версий PHP, то вам необходимо
  [сообщить о них](https://github.com/yiiext/activerecord-relation-behavior/issues).
* Поведение работает только с AR-классами, у которых есть первичный ключ. Если ваша таблица в базе данных не имеет
  первичного ключа, то вы должны задать его сами в методе
  [primaryKey()](http://www.yiiframework.com/doc/api/1.1/CActiveRecord#primaryKey%28%29-detail) AR-класса.
* _Для модульного тестирования необходимо наличие PHP версии 5.3.0 или выше._


## Установка

1. Получить расширение можно одним из следующих способов:
   * [Скачайте](https://github.com/yiiext/activerecord-relation-behavior/tags) последнюю версию и распакуйте файлы
     в директорию `extensions/yiiext/behaviors/activerecord-relation/` относительно корня вашего приложения.
   * Добавьте репозиторий расширения как git submodule в вашем основном репозитории приложения следующим образом:
     `git submodule add https://github.com/yiiext/activerecord-relation-behavior.git extensions/yiiext/behaviors/activerecord-relation`
2. Для того, чтобы можно было работать с данным расширением вам необходимо добавить следующий код в метод `behaviors()`
   модели:

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


## Да свершится чудо!

Рассмотрим следующие две модели
(они объявлены [в руководстве Yii](http://www.yiiframework.com/doc/guide/1.1/ru/database.arr#sec-2)):
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

В коде приложения можно осуществлять следующие операции:
```php
<?php
    $user = new User();
    $user->posts = array(1,2,3);
    $user->save();
    // пользователь теперь является автором постов 1, 2 и 3

    // пример выше эквивалентен следующему примеру:
    $user = new User();
    $user->posts = Post::model()->findAllByPk(array(1,2,3));
    $user->save();
    // пользователь теперь является автором постов 1, 2 и 3

    $user->posts = array_merge($user->posts, array(4));
    $user->save();
    // пользователь теперь автор и поста под номером 4 тоже

    $user->posts = array();
    $user->save();
    // пользователь теперь не имеет постов (ни один пост не принадлежит ему)

    $post = Post::model()->findByPk(2);
    $post->author = User::model()->findByPk(1);
    $post->categories = array(2, Category::model()->findByPk(5));
    $post->save();
    // пост 2 теперь имеет автора под номером 1 и принадлежит к категориям 1 и 5

    // добавление профиля пользователя:
    $user->profile = new Profile();
    $user->profile->save(); // необходимо для того, чтобы точно быть уверенным в том, что профиль имеет первичный ключ
    $user->save();
```


## Моменты, о которых необходимо помнить

* При использовании данного поведения вы не можете явно присваивать какие-то значения самим столбцам внешних ключей.
  К примеру, если вы присвоите какое-то значение свойству `$model->author_id`, то это ничего не изменит, потому как
  данное поведение при сохранении присвоит ему значение `null` (если вы ничего не присвоили в отношении).
  Вместо этого просто присваивайте нужные значение самим отношениям: `$model->author = 1;` / `$model->author = null;`.
* Отношения не будут обновлены после сохранения в них данных. То есть если вы просто установили первичные
  ключи в отношении до сохранения, то после сохранения объектов в нём не будет. Вызывайте `$model->reload()`
  для форсирования перезагрузки связанных данных. Другим способом является форсированное получение связанных данных
  следующим образом: `$model->getRelated('relationName',true)`.
* Данное поведение работает только с отношениями, которые не имеют дополнительных условий, JOINов, группировок.
  При присваивании и сохранении данных у таких отношений конечное поведение и результат не всегда однозначно ясны.
* Если вы присваиваете запись к отношению BELONGS_TO (например так: `$post->author = $user;`), то в этом случае
  данные отношения `$user->posts` не будут обновлены автоматически (возможно, что поддержка этого будет
  добавлена в будущем).


## Описание исключений

### "You can not save a record that has new related records!"

Отношению была присвоена ещё не сохранённая запись (её не существует в базе данных). Это не будет работать, потому как
поведение ActiveRecord Relation для сохранения связанных данных требует наличия у них первичного ключа. Перед
присваиванием записей отношению необходимо их полностью сохранить вызовом метода `save()`.

### "A HAS_MANY/MANY_MANY relation needs to be an array of records or primary keys!"

Отношениям HAS_MANY и MANY_MANY нужно присваивать только массивы. Присваивание одиночных записей (не массивов) к таким
отношениям невозможн и бессмысленно.

### "Related record with primary key "X" does not exist!"

Исключение вызвано тем фактом, что вы попытались присвоить отношению несуществующий в базе данных первичный ключ.


## Сравнение возможностей

Следующие возможности были взяты из других расширений:

- Возможность сохранения MANY_MANY отношений (cadvancedarbehavior, eadvancedarbehavior, esaverelatedbehavior
и advancedrelationsbehavior).
- Возможность сохранения BELONGS_TO, HAS_MANY и HAS_ONE отношени (eadvancedarbehavior, esaverelatedbehavior
и advancedrelationsbehavior).
- Сохранение произодится с использованием транзакции, при этом есть поддержка работы с внешними транзакциями
(with-related-behavior, esaverelatedbehavior и saverbehavior).
- Не производит изменений в дополнительных данных связующей таблицы отношения MANY_MANY (cadvancedarbehavior удаляет их).
- Проверяет тип данных, присвоенный отношению. К отношениям HAS_MANY и MANY_MANY нельзя присвоить не массивы. Это
делает семантику более понятной.

Изученные расширения, которые принесли пользу:
- cadvancedarbehavior        http://www.yiiframework.com/extension/cadvancedarbehavior/
- eadvancedarbehavior        http://www.yiiframework.com/extension/eadvancedarbehavior
- advancedrelationsbehavior  http://www.yiiframework.com/extension/advancedrelationsbehavior
- saverbehavior              http://www.yiiframework.com/extension/saverbehavior
- with-related-behavior      https://github.com/yiiext/with-related-behavior
- CSaveRelationsBehavior     http://code.google.com/p/yii-save-relations-ar-behavior/
- esaverelatedbehavior       http://www.yiiframework.com/extension/esaverelatedbehavior

Были изучены следующие расширения, но ничего полезного почерпнутно не было:
- xrelationbehavior          http://www.yiiframework.com/extension/xrelationbehavior
- save-relations-ar-behavior http://www.yiiframework.com/extension/save-relations-ar-behavior

Большое спасибо авторам всех этих расширений за идеи и предложения.


## Запуск модульных тестов

Поведение на 100% покрыто модульными тестами (класс ECompositeDbCriteria покрыт не полностью потому как композитные
первичные ключи пока не поддерживаются полностью). Для запуска модульных тестов вам нужен установленный
[PHPUnit](https://github.com/sebastianbergmann/phpunit#readme). Тестовые классы расширения требуют PHP версии 5.3
или выше.

1. Убедитесь, что дистрибутив Yii доступен по пути `./yii/framework`. Вы можете добиться этого следующими способами:
   - Ссклонировав Git репозиторий при помощи команды `git clone https://github.com/yiisoft/yii.git yii`.
   - Создав символическую ссылку на уже существующую директорию с Yii `ln -s ../../path/to/yii yii`.
2. Выполните команду `phpunit EActiveRecordRelationBehaviorTest.php`. Если вы хотите получить информацию о покрытии
   кода в формате HTML, то команда, осуществляюшая это будет выглядеть следующим образом:
   `phpunit --coverage-html tmp/coverage EActiveRecordRelationBehaviorTest.php`


## Часто задаваемые вопросы

### Пересохраняются ли неизменённые связанные данные в MANY_MANY отношении?

Для проверки того, были ли загружены или установлены явно связанные данные в поведении используется метод
``CActiveRecord::hasRelated()``. Сохранение произойдёт в случае, если эти связанные данные существуют в отношении.
Поведение не может однозначно определить были ли изменены данные в отношении.

Пересохранение не означает, что все соответствующие записи в связующей таблице удаляются, а потом вставляются заново.
Удаление данных в связующей таблице происходит только тогда, когда она отсутсвует в отношении, но есть
в базе данных. Если вы ничего не меняли, то ничего и не изменится.

### Существует ли возможность сохранения записей только в связующей таблице без повторного сохранения связываемых
моделей?

Сейчас нет, но эта возможность будет добавлена в будущем;
[тикет #16](https://github.com/yiiext/activerecord-relation-behavior/issues/16).

### Каким образом я могу удалить одну определённую связующую строчку в MANY_MANY отношении? Нужно ли мне для этого
загружать все связанные модели?

На данный момент вы должны загрузить все связанные модели и переназначить массив с отношениями. API для этого будет
добавлено в будущем; [тикет #16](https://github.com/yiiext/activerecord-relation-behavior/issues/16).
