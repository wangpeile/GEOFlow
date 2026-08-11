# GEOFlow 独立测试环境

测试环境复用生产镜像结构（Nginx、PHP-FPM、队列、调度器、Reverb、PostgreSQL、Redis），但使用独立的容器、端口、网络、数据库目录和 storage 目录，不会覆盖生产数据。

## 首次启动

```bash
cp .env.test.example .env.test
php -r '$path=".env.test"; $env=file_get_contents($path); $env=preg_replace("/^APP_KEY=.*$/m", "APP_KEY=base64:".base64_encode(random_bytes(32)), $env); file_put_contents($path, $env);'
```

修改 `.env.test` 中所有 `change-this-*` 值后执行：

```bash
docker compose --env-file .env.test -f docker-compose.prod.yml -f docker-compose.test.yml build
docker compose --env-file .env.test -f docker-compose.prod.yml -f docker-compose.test.yml up -d
docker compose --env-file .env.test -f docker-compose.prod.yml -f docker-compose.test.yml ps
```

访问：

- 网站：`http://localhost:18082`
- 管理后台：`http://localhost:18082/geo_admin`
- 内容生产工作台：`http://localhost:18082/geo_admin/content-productions`
- Reverb：`http://localhost:18083`

## 验证

```bash
curl -fsS http://localhost:18082/up
docker compose --env-file .env.test -f docker-compose.prod.yml -f docker-compose.test.yml exec app php artisan migrate:status
docker compose --env-file .env.test -f docker-compose.prod.yml -f docker-compose.test.yml exec app php artisan about
```

## 停止

```bash
docker compose --env-file .env.test -f docker-compose.prod.yml -f docker-compose.test.yml down
```

如需连同测试数据库和 Redis 数据一起重置：

```bash
docker compose --env-file .env.test -f docker-compose.prod.yml -f docker-compose.test.yml down -v
```
