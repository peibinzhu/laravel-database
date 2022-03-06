# 创建模型

此功能移植于 Hyperf 的生成模型命令功能，可以很方便的根据数据表创建对应模型。命令通过 AST 生成模型，所以当您增加了某些方法后，也可以使用脚本方便的重置模型。

```bash
php artisan gen:model table_name
```

可选参数如下：

|       参数        |   类型   |                 默认值                  |备注 |
|:---------------:|:------:|:------------------------------------:|:-----------------:|
|   --database    | string |               `mysql`                | 数据库名称，脚本会根据数据库配置创建 |
|     --path      | string |             `app/Models`             |       模型路径        |
|    --prefix     | string |                 空字符串                 | 表前缀 |
|  --inheritance  | string |               `Model`                | 父类 |
|     --uses      | string | `Illuminate\Database\Eloquent\Model` | 配合 `inheritance` 使用 |
| --table-mapping | array  |                 `[]`                 | 为表名 -> 模型增加映射关系 比如 ['users:Account'] |
| --ignore-tables | array  |                 `[]`                 | 不需要生成模型的表名 比如 ['users'] |
| --with-comments |  bool  |               `false`                | 是否增加字段注释  |
|   --database    | string |               `mysql`                | 数据库名称，脚本会根据数据库配置创建 |

对应配置也可以配置到 `database.connections.{pool}.commands.gen:model` 中，如下

```php
<?php

declare(strict_types=1);

return [
    'default' => [
        // 忽略其他配置
        'commands' => [
            'gen:model' => [
                'path' => 'app/Models',
                'inheritance' => 'Model',
                'uses' => '',
                'table_mapping' => [],
                'with_comments' => true,
            ],
        ],
    ],
];
```

创建的模型如下

```php
<?php

declare (strict_types=1);

namespace Illuminate\Database\Eloquent\Model;

use App\Models\Model;

/**
 * @property $id
 * @property $name
 * @property $gender
 * @property $created_at
 * @property $updated_at
 */
class UserLoginMng extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'default';
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user';
}
```

## 模型成员变量

|     参数      |   类型   |   默认值   |         备注          |
|:----------:|:-----:|:------:|:------------------:|
| connection  | string | default |        数据库连接        |
|    table    | string |    无    |        数据表名称        |
