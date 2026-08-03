# GEOFlow 本地 Embedding：Ollama + bge-m3 + Qdrant

## 架构与当前边界

- GEOFlow 通过 Ollama 的 OpenAI 兼容接口 `http://ollama:11434/v1/embeddings` 生成 bge-m3 向量。
- Ollama 与 Qdrant 使用 `docker-compose.local-ai.yml` 部署，并加入 GEOFlow 的生产 Docker 网络。
- Ollama 和 Qdrant 的宿主机端口只绑定 `127.0.0.1`，不会直接暴露到局域网。
- Ollama 云端功能和 Qdrant 遥测均已显式关闭。
- Ollama 模型保存在 `docker-data/prod/ollama`；Qdrant 使用 `geoflow-qdrant-data` Docker 命名卷，避免 macOS/OrbStack 的 FUSE 绑定目录缓存风险。
- GEOFlow 当前仍使用 PostgreSQL/pgvector 保存和检索知识库向量。Qdrant 可独立存取向量，但配置层尚不能让 GEOFlow 将文档写入 Qdrant。

## 启动

```bash
docker compose --env-file .env.prod -f docker-compose.local-ai.yml up -d
docker exec geoflow-ollama ollama pull bge-m3
```

停止服务但保留模型和向量数据：

```bash
docker compose --env-file .env.prod -f docker-compose.local-ai.yml stop
```

## GEOFlow 配置

在 `.env.prod` 中允许应用访问精确的私网目标：

```env
GEOFLOW_OUTBOUND_PRIVATE_TARGETS=ollama:11434
```

然后重建 GEOFlow 配置缓存并重启常驻进程。

在后台“AI 配置器 → AI 模型”中创建：

- 名称：`Local bge-m3`
- 模型类型：`Embedding`
- 模型 ID：`bge-m3`
- API Base URL：`http://ollama:11434/v1`
- API Key：任意非空本地占位值，例如 `ollama-local`（Ollama 不校验该值）
- 状态：`启用`

测试连接成功后，将它设为默认 Embedding 模型。对知识库执行“更新切片”即可生成真实向量。

## API 验证

```bash
curl http://127.0.0.1:11434/v1/embeddings \
  -H 'Content-Type: application/json' \
  -d '{"model":"bge-m3","input":["GEOFlow 本地向量测试"]}'

curl http://127.0.0.1:6333/healthz
```

Qdrant 管理页面：[http://127.0.0.1:6333/dashboard](http://127.0.0.1:6333/dashboard)

bge-m3 的 dense 向量维度为 1024。创建 Qdrant 测试集合：

```bash
curl -X PUT http://127.0.0.1:6333/collections/geoflow_bge_m3 \
  -H 'Content-Type: application/json' \
  -d '{"vectors":{"size":1024,"distance":"Cosine"}}'
```

将 Ollama 返回的真实向量写入 Qdrant，并读取确认：

```bash
curl -s http://127.0.0.1:11434/v1/embeddings \
  -H 'Content-Type: application/json' \
  -d '{"model":"bge-m3","input":"GEOFlow 本地知识库向量测试"}' \
  | jq '{points:[{id:1,vector:.data[0].embedding,payload:{source:"local-bge-m3"}}]}' \
  | curl -X PUT 'http://127.0.0.1:6333/collections/geoflow_bge_m3/points?wait=true' \
      -H 'Content-Type: application/json' --data-binary @-

curl 'http://127.0.0.1:6333/collections/geoflow_bge_m3/points/1?with_payload=true'
```

## 离线运行

镜像和 `bge-m3` 已下载后，Embedding 和向量检索运行期不需要互联网。建议固定镜像版本或 digest，避免未来重新拉取 `latest` 时出现不可控升级。
