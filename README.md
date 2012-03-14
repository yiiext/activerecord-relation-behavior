Readme coming soon...

#Install


#How to use


#What can I do?


#What can't I do?

* once you use this behavior you cannot set relations by setting related key values anymore
for example if you set $model->author_id it will have no effect since ARRelationBehavior will overwrite it
  with null if there is no related record or set it to related records primary key.
  instead simply assign the value to the relation: $model->author = 1; / $model->author = null;

#Best practise


#Exceptions explained

