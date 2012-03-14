Readme coming soon...

#Install


you need php 5.3 to run the test.
behavior should work on PHP 5.1.0 or above without problems
I try to be at least that backwards compatible as Yii is, which is PHP 5.1.0 , if there are any problems with php versions, please report!

#How to use

 * - This will only work for AR that have PrimaryKey defined!
 make sure you at least overrided primaryKey()


#What can I do?

 * reloading relations:
 * if you saved a BELONGS_TO relation you have to reload the corresponding HAS_ONE relation on the object you set.
 * if you saved a...
 *
 *
 *


#What can't I do?

* once you use this behavior you cannot set relations by setting related key values anymore
for example if you set $model->author_id it will have no effect since ARRelationBehavior will overwrite it
  with null if there is no related record or set it to related records primary key.
  instead simply assign the value to the relation: $model->author = 1; / $model->author = null;

#Best practise


#Exceptions explained

