
Cache: doc à corriger
- ArrayCache: OK
- FileCache: OK
- RedisCache: tests à faire
- MemcachedCache: tests à faire
- cache rate limiter: test OK > remove configuration part from the component doc, or make it clearer this is for the framework
- lock rate limiter: idem que rate limiter

ConfigRepo: test et doc ok

Container: 
- a besoin de plus de tests unitaires, doc OK (à vérifier)
- supporter method injection, pas juste constructor injection

QueryBuilder: 
- move test in a trait and have tests for SQLite and MySQL
- whereFields (in the doc but not in the implementation)?
- use enums for order by

DateTime: 
- tests majoritairement à faire
- doc à faire

EventDispatcher: doc OK, tests à faire

File System: docs et tests à faire
- ArrayFileSystem
- LocalFileSystem
- FtpFileSystem

HTTPClient: doc et tests à faire
- CurlHttpClient
- StreamContextHttpClient (à implémnter)

Log: le tout à revoir, 
- DailyFileLogger à bouger dans le framework ?
- add DB logger

Validation: doc et tests OK

ViewRenderer: 
- devrait être dans le framework, ou alors le chemin vers les vues non hardcodés
- vérif support de extends


Entity: 
- support JsonSerialize, getters in toArray(), and setters in the static constructor
- doc 
- tests

Translation repository: 
- mettre en composant, sans utiliser le config repo
- mettre dans un composant Misc, avec Entity, config

Framework: 
- deprecate "at string" in favor of ['FQCN', 'non static method name']
- actually build something with it

Identifier: tests et doc OK

Encrypter: tests et doc OK
