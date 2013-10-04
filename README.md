## Замечания
* Все поля в api являются обязательными, если не указано обратного.
* Все методы в случае ошибки возвращают следующую структуру:
```js
{"status": "error", "message": "error message text"}
```
* Внутренняя структура api в некоторой части продиктована текущим строением [webmail](https://github.com/mediasite/webmail).

## POST `/createUser`
Создает пользователя. Если имя пользователя содержит символ `@`, тогда оно используется для имени email. Иначе - присваивает ему ящик вида `userName@defaultMailDomain`. `defaultMailDomain` указывается в [настройках dbmail-api](https://github.com/mediasite/dbmail-api/blob/master/protected/config/params-dist.php).
### Принимает:
* `userName`, string - Имя пользователя в dbmail и по совместительству, email пользователя.
* `password`, string - Пароль пользователя.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/changePassword`
Изменяет пароль существующего пользователя.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.
* `password`, string - Пароль пользователя.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/deleteUser`
Удаляет существующего пользователя и все его ящики со всей почтой.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/truncateUser`
Очищает у существующего пользователя все его ящики со всей почтой.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/getUnreadCount`
Возвращает количество непрочитанных писем у пользователя.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.

### Возвращает в случае успешной работы:
```js
{"status": "ok", "unreadCount": 0}
```

## POST `/createRule`
Создает правила фильтрации входного почтового потока.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.
* `ruleName`, string - Название правила.
* `rulesJoinOperator`, string - Логический оператор, который будет применятся 
* `rules`, string, json array - Правила фильтра. Формат:
```js
[
    {
        attribute: rule,
    },
    {
        ["From" | "Subject" | "Any To or Cc", "X-Spam-Flag"]: {
            "operation": ["is" | "is not"],
            "value": ["compare string", "*substring*"]
        },
    },
    {
        "Message Size": {
            "operation": ["is" | "is not" | "less than" | "greater than"],
            "value": bytesInteger,
        },
    },
    ...
]
```
Возможно фильтровать по слеующим полям в сообщении:
 * `From` - Отправитель. Поле From в оригинальном сообщении.
 * `Subject` - Тема сообщения. Поле Subject в оригинальном сообщении.
 * `Any To or Cc` - Адресаты. Поля Cc или To в оригинальном сообщении. 
 * `X-Spam-Flag` - Флаг спама. Поле X-Spam-Flag в оригинальном сообщении.

    При проверке размера сообщения (`attribute` == `Message Size`), операции сравнения меньше чем (`less than`) и больше чем (`greater than`) являются строгими.
* `actions`, string, json object - Действие с письмами. Допускается одно действие. Формат:
```js
{
    action: attribute
}
```
```js
{
    ["Discard", "Mark", "Store in"]: "attribute"
}
```
где `action` может принимать следующие значения:
 * `Discard` - Удалить письмо. `attribute` при этом игнорируется.
 * `Mark` - Пометить письмо прочтенным (`attribute` == `Read`) либо избранным (`attribute` == `Flagged`).
 * `Store in` - Переместить в папку `attribute`.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/deleteRule`
Удаляет правило существующее правило фильтрации.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.
* `ruleName`, string - Название правила.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/getRules`
Возвращает все активные правила у конкретного пользователя.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.

### Возвращает в случае успешной работы:
```js
{
    "status": "ok",
    "rules": {
        "ruleName1": {
            "rules": [
                ...
            ],
            "actions": {
                ...
            },
            "rulesJoinOperator" : "[and | or]"
        }, 
        ...
    }
}
```
Где `rules` - массив объектов с активными правилами. Ключ - идентификатор правила, значение - объект правила со свойствами `rules` и `actions`. Структура объектов `rules` и `actions` описана в [/createRule](Api#post-createrule)

## POST `/addGetMailRule`
Создает правило для сборщика почты по протоколу POP3.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.
* `host`, string - Хост pop3 сервера.
* `email`, string - Логин для авторизации на pop3 сервере.
* `password`, string - Пароль для авторизации.
* `delete`, необязательное, string, по умолчанию = "0". Установить в "1", если требуется удалять почту с удаленного почтового ящика после сбора.

### Возвращает в случае успешной работы:
```js
{"status": "ok", "ruleId": "2"}
```
Где `ruleId` - id созданного правила, string.

## POST `/editGetMailRule`
Изменяет правило для сборщика почты по протоколу POP3.
### Принимает:
* `id`, int - Id ранее созданного правила.
* `userName`, string - Имя пользователя в dbmail.
* `host`, string - Хост pop3 сервера.
* `email`, string - Логин для авторизации на pop3 сервере.
* `password`, необязательное, string - Пароль для авторизации. Если не указан - используется ранее заданный пароль.
* `delete`, необязательное, string, по умолчанию = "0". Установить в "1", если требуется удалять почту с удаленного почтового ящика после сбора.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/removeGetMailRule`
Удаляет правило существующее правило сборщика почты.
### Принимает:
* `ruleId`, string - Id правила.

### Возвращает в случае успешной работы:
```js
{"status": "ok"}
```

## POST `/listGetMailRules`
Возвращает все правила сборщика почты.
### Принимает:
* `userName`, string - Имя пользователя в dbmail.

### Возвращает в случае успешной работы:
```js
{
    "status": "ok",
    "rules": [
        {
            "id": "1",
            "host": "pop.mail.ru",
            "email": "mymail@mail.ru",
            "password": "strongPassword",
            "dbMailUserName": "userName",
            "delete": "0",
            "ssl": "0",
            "status": "1"
        },
        ...
    ]
}
```
Где:
* `id` - Id правила,  
* `host`, `email`, `password`, `delete` соответствуют полям в запросе на создание правила (`/addGetMailRule`)  
* `dbMailUserName` - имя пользователя в dbmail,
* `ssl` - Правило использует ssl содинение ("1") или обычное ("0")
* `status` может принимать следующие значения:
  * "0" - Правило работает успешно.
  * "1" - Правило еще не отрабатывалось.
  * "2" - Неправильный пароль.
  * "3" - Ошибка соединения с сервером pop3.
  * "4" - Неправильный домен pop3 сервера.
  * "5" - Неизвестная ошибка.