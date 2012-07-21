# Поведение ActiveRecord Relation [![Build Status](https://secure.travis-ci.org/yiiext/activerecord-relation-behavior.png)](http://travis-ci.org/yiiext/activerecord-relation-behavior)

Поведение создано на основе идей из других расширений, упрощающих сохранение связанных данных (смотрите раздел
«Сравнение возможностей»). Главным образом упрощается работа с MANY_MANY отношениями. Код полностью покрыт модульными
тестами, хорошо структурирован и чист — это значит, что расширение может использоваться в реальных приложениях масштаба
предприятия.


## Требования

* Yii версии 1.1.6 или выше.
* Как и сам фреймворк данное расширение совместимо с PHP версии 5.1.0 или выше. Если у вас возникли проблемы
  с поддержкой определённых версий PHP, то вам необходимо
  [сообщить о них](https://github.com/yiiext/activerecord-relation-behavior/issues).
* Поведение работает только с AR-классами, у которых есть первичный ключ. Если в таблице базы данных не был определён
  первичный ключ, то вы должны задать его в методе
  [primaryKey()](http://www.yiiframework.com/doc/api/1.1/CActiveRecord#primaryKey%28%29-detail) AR-класса.
* _Для модульного тестирования необходимо наличие PHP версии 5.3.0 или выше._


## Установка

1. Получить расширение можно одним из следующих способов:
   * [Скачайте](https://github.com/yiiext/activerecord-relation-behavior/tags) последнюю версию и распакуйте файлы
     в директорию `extensions/yiiext/behaviors/activerecord-relation/` относительно корня вашего приложения.
   * Добавьте репозиторий расширения как git submodule в репозитории приложения следующим образом:
     `git submodule add https://github.com/yiiext/activerecord-relation-behavior.git extensions/yiiext/behaviors/activerecord-relation`
2. Для активации данного поведения вам необходимо добавить следующий код в метод `behaviors()` нужного AR-класса:

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
(они используются и [в руководстве Yii](http://www.yiiframework.com/doc/guide/1.1/ru/database.arr#sec-2)):
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

В коде приложения работа с поведением выглядит так:
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
    // пользователь теперь не имеет постов (ни один пост не принадлежит ему, как автору)

    $post = Post::model()->findByPk(2);
    $post->author = User::model()->findByPk(1);
    $post->categories = array(2, Category::model()->findByPk(5));
    $post->save();
    // пост 2 теперь имеет автора под номером 1 и принадлежит к категориям 1 и 5

    // добавление профиля пользователя:
    $user->profile = new Profile();
    $user->profile->save(); // необходимо для того, чтобы быть уверенным в том, что профиль имеет первичный ключ
    $user->save();
```


## Моменты, о которых необходимо помнить

* При использовании данного поведения вы не можете явно присваивать какие-то значения самим столбцам внешних ключей.
  К примеру, если вы присвоите какое-то значение свойству `$model->author_id`, то это ничего не сделает, потому как
  данное поведение при сохранении присвоит ему значение `null` (если вы ничего не присвоили отношению внешнего ключа).
  Вместо этого просто присваивайте нужные значение самим отношениям: `$model->author = 1;` / `$model->author = null;`.
* Отношения не будут обновлены после их сохранения, то есть если вы просто установили первичные ключи в отношении
  до сохранения, то после сохранения реальных объектов в нём не будет. Вызывайте `$model->reload()` для форсирования
  перезагрузки связанных данных. Другим способом является форсированное получение связанных данных:
  `$model->getRelated('relationName',true)`.
* Данное поведение работает только с отношениями, которые не имеют дополнительных условий, JOINов и группировок
  (GROUP). Результат присваивания и сохранения связанных данных у отношений такого рода не всегда однозначно
  понятен.
* Если вы присваиваете запись к отношению BELONGS_TO (например так: `$post->author = $user;`), то в этом случае
  данные отношения `$user->posts` не будут обновлены автоматически (возможно, что поддержка этого будет
  добавлена в будущем).


## Описание исключений

### You can not save a record that has new related records!

Отношению была присвоена ещё не сохранённая запись (её не существует в базе данных). Это не будет работать, потому как
поведение ActiveRecord Relation для сохранения связанных данных требует наличия у них первичного ключа. Перед
присваиванием множества записей к какому-либо отношению необходимо это множество полностью сохранить вызовом метода
`save()` у каждой модели.

### A HAS_MANY/MANY_MANY relation needs to be an array of records or primary keys!

Отношениям HAS_MANY и MANY_MANY можно присваивать только массивы. Присваивание одиночных записей (не массивов) к таким
отношениям невозможно и лишено смысла.

### Related record with primary key "X" does not exist!

Исключение вызвано тем фактом, что вы попытались присвоить отношению несуществующий в базе данных первичный ключ.


## Сравнение возможностей

Следующие возможности были заимствованы из других расширений:

- Возможность сохранения данных MANY_MANY отношений (cadvancedarbehavior, eadvancedarbehavior, esaverelatedbehavior
и advancedrelationsbehavior также умеют это).
- Возможность сохранения данных BELONGS_TO, HAS_MANY и HAS_ONE отношений (eadvancedarbehavior, esaverelatedbehavior
и advancedrelationsbehavior также умеют это).
- Сохранение произодится с использованием транзакции, при этом есть поддержка работы с внешними транзакциями
(with-related-behavior, esaverelatedbehavior и saverbehavior также умеют это).
- Не производит изменений в дополнительных данных связующей таблицы отношения MANY_MANY (cadvancedarbehavior удаляет их).
- Проверяет тип данных, присваиваемый отношению. К отношениям HAS_MANY и MANY_MANY можно присваивать только массивы,
в угоду более однозначной семантики.

Изученные расширения, которые принесли пользу:
- cadvancedarbehavior        http://www.yiiframework.com/extension/cadvancedarbehavior/
- eadvancedarbehavior        http://www.yiiframework.com/extension/eadvancedarbehavior
- advancedrelationsbehavior  http://www.yiiframework.com/extension/advancedrelationsbehavior
- saverbehavior              http://www.yiiframework.com/extension/saverbehavior
- with-related-behavior      https://github.com/yiiext/with-related-behavior
- CSaveRelationsBehavior     http://code.google.com/p/yii-save-relations-ar-behavior/
- esaverelatedbehavior       http://www.yiiframework.com/extension/esaverelatedbehavior

Были изучены следующие расширения, но ничего полезного взято не было:
- xrelationbehavior          http://www.yiiframework.com/extension/xrelationbehavior
- save-relations-ar-behavior http://www.yiiframework.com/extension/save-relations-ar-behavior

Большое спасибо авторам всех этих расширений за их идеи и предложения.


## Запуск модульных тестов

Поведение полностью покрыто модульными тестами (класс ECompositeDbCriteria покрыт не полностью потому как составные
первичные ключи пока не поддерживаются). Для запуска модульных тестов вам нужен установленный
[PHPUnit](https://github.com/sebastianbergmann/phpunit#readme). Тестовые классы расширения требуют PHP версии 5.3
или выше.

1. Убедитесь в том, что дистрибутив Yii доступен по пути `./yii/framework`. Вы можете добиться этого следующими способами:
   - Склонировав Git репозиторий при помощи команды `git clone https://github.com/yiisoft/yii.git yii`.
   - Создав символическую ссылку на уже существующую директорию с Yii `ln -s ../../path/to/yii yii`.
2. Выполните команду `phpunit EActiveRecordRelationBehaviorTest.php`. Если вы хотите получить информацию о покрытии
   кода в виде HTML файлов, то команда будет выглядеть следующим образом:
   `phpunit --coverage-html tmp/coverage EActiveRecordRelationBehaviorTest.php`


## Часто задаваемые вопросы

### Пересохраняются ли неизменённые связанные данные в MANY_MANY отношении?

Для проверки того, были ли загружены или установлены явно связанные данные в поведении используется метод
`CActiveRecord::hasRelated()`. Сохранение произойдёт в случае, если эти связанные данные существуют в отношении.
Поведение не может однозначно определить были ли изменены данные в отношении явно или нет.

Пересохранение не означает, что все соответствующие записи в связующей таблице удаляются, а потом добавляются заново.
Удаление данных в связующей таблице происходит только тогда, когда они отсутствуют в отношении, но существуют
в базе данных. Если вы ничего не меняли, то ничего и не изменится.

### Существует ли возможность сохранения записей только в связующей таблице без повторного сохранения связываемых
моделей?

Сейчас нет, но эта возможность будет добавлена в будущем;
[тикет #16](https://github.com/yiiext/activerecord-relation-behavior/issues/16).

### Каким образом я могу удалить одну определённую связующую строчку в MANY_MANY отношении? Нужно ли мне для этого
загружать все связанные модели?

На данный момент вы должны загрузить все связанные модели и переназначить массив с отношениями. API для этого будет
добавлено в будущем; [тикет #16](https://github.com/yiiext/activerecord-relation-behavior/issues/16).
