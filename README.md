# Опросы/голосования Bitrix (альтернативный API)

## Установка

```bash
composer require beta/bx.vote
```

[Базовая реализация](https://github.com/beta-eto-code/base.vote)

В данном модуле реализованы сервисы:

* BitrixVoteService - реализация интерфейса VoteServiceInterface, предназначен для работы со схемой опроса (сохранение схемы, запрос схем(ы) по заданным критериям).
* BitrixVoteResultService - реализация интерфейса VoteResultServiceInterface, предназначен для работы с результатами опроса (сохранение результата, запрос результатов по заданным критериям).
* VoteService - инкапсулириует оба вышеперчисленных сервиса.

## Пример выборки опросов по заданным критериям

```php
use Bx\Vote\VoteService;
use Bx\Model\MappedCollection;
use Base\Vote\Interfaces\VoteSchemaInterface;

$voteService = new VoteService();
$voteSchemaCollection = $voteService->getVoteSchemasByCriteria(
    [
        '=ID' => [1,2,3,4,5,6,7,10],
        '=ACTUAL_FOR' => 1,      // фильтр для выборки опросов которые не были пройдены пользователем с идентификатором 1
        '=IS_SINGLE' => 'Y',    // фильтр для выборки опросов с одним вопросом
    ],
    10,                         // лимит опросов в выборке
    2                           // выборка опросов от указанной позиции
);

$voteSchemaCollection->first(); // вернет первый опрос из коллекции

$voteSchemaCollection->column('id'); // список идентификаторов

$voteSchemaCollection->unique('channel_id'); // список не повторяющихся значений (групп оросов)

$voteSchemaCollection->column('title', 'id'); // массив ключом в котором выступает идентификатор опроса а значением название

$voteSchemaCollection->findByKey('id', 5); // вернет опрос с идентификатором 5

$voteSchemaCollection->filterByKey('channel_id', 1); // вернет коллекцию опросов из группы с идентификатором 1

$voteSchemaCollection->find(function(VoteSchemaInterface $voteSchema) {
    return $voteSchema->getQuestionCount() === 3;
}); // вернет первый найденный опрос с 3 вопросами

$voteSchemaCollection->filter(function(VoteSchemaInterface $voteSchema) {
    return $voteSchema->getQuestionCount() === 3;
}); // вернет коллекцию опросов с 3 вопросами

foreach($voteSchemaCollection as $voteSchema) {
    /// Некоторый код для работы с каждым опросом
}

$voteSchemaMappedCollection = new MappedCollection($voteSchemaCollection, 'id'); // создаем коллекцию у которой в роли ключей выступают идентификаторы опросов
$someVoteSchema = $voteSchemaMappedCollection[5]; // опрос с идентификатором 5
```

## Пример работы с опросом

```php
use Bx\Vote\VoteService;
use Base\Vote\VoteSchema;

$voteService = new VoteService();
$voteSchema = $voteService->getVoteSchemaById(1);   // запрашиваем опрос по идентификатору

$questionCollection = $voteSchema->getQuestions(); // коллекция опросов

foreach($questionCollection as $question) {
    /// Некоторый код для работы с каждым вопросом
}

$firstQuestion = $questionCollection->first(); // первый вопрос из коллекции

$answerVariantsCollection = $firstQuestion->getAnswerVariants(); // коллекция вариантов ответа

foreach($answerVariantsCollection as $answerVariant) {
    /// Некоторый код для работы с каждым вариантом ответа
}

$firstAnswerVariant = $answerVariantsCollection->first(); // первый вариант ответа из коллекции

$jsonSchema = json_encode($voteSchema); // JSON представление опроса
$recoveredVoteSchema = new VoteSchema(json_decode($jsonSchema, true)); // собираем объект опроса из JSON представления
```

## Пример выборки результатов опроса

```php
use Bx\Vote\VoteService;

$voteService = new VoteService();
$voteSchema = $voteService->getVoteSchemaById(1); 
$voteResultCollection = $voteService->getVoteResultList(
    $voteSchema, 
    [/** параметры для выборки **/]
);  // коллекция результатов указанного опроса

$firstVoteResult = $voteResultCollection->first(); // первый результат опроса из коллекции

$answerResultCollection = $firstVoteResult->getAnswerResults(); // коллекция ответов на вопросы

$firstAnswerResult = $answerResultCollection->first(); // первый ответ из коллекции

$answerVariant = $firstAnswerResult->getAnswerVariant(); // выбранный вариант ответа на вопрос

$question = $answerVariant->getQuestion(); // вопрос

foreach($answerVariantsCollection as $answerVariant) {
    /// Некоторый код для работы с каждым вариантом ответа
}
```

## Пример добавления результата опроса:

```php
use Bx\Vote\VoteService;
use Base\Vote\Interfaces\VoteResultServiceInterface;
use Base\Vote\Interfaces\QuestionInterface;
use Base\Vote\VoteResult;

$voteService = new VoteService();
$voteSchema = $voteService->getVoteSchemaById(1);
$newVoteResult = new VoteResult($voteSchema);

// Добавление ответа
$newVoteResult->createAnswerResultByTitle(
    'Планируете ли вы дополнительно обучаться профильной специальности?', 
    'да'
);

// Добавление того же ответа другим методом
$question = $voteSchema->getQuestionByTitle('Планируете ли вы дополнительно обучаться профильной специальности?');
$answerVariant = $question instanseof QuestionInterface ? $question->getAnswerVariantByTitle('да') : null;
$newVoteResult->createAnswerResult($answerVariant);

$result = $voteService->saveVoteResult($newVoteResult);
```