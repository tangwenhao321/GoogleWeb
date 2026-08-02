#!/usr/bin/env bash
#
# auigetvapeshop.com — Cloudflare 性能优化自动化脚本（对应审计报告 1.4 节）
#
# 功能：
#   1. 开启 Brotli 压缩
#   2. 开启 Auto Minify（CSS/JS/HTML）
#   3. 为静态资源目录（/wp-content/uploads/*, /wp-content/themes/*, /wp-content/plugins/*）
#      新增一条 Page Rule：Edge Cache TTL 拉长到 1 个月，Browser Cache TTL 1 年
#
# 前置条件（必须先在 Cursor Dashboard -> Cloud Agents -> Secrets 里配置好，
# 再作为环境变量传入，否则脚本会直接报错退出，不会用明文凭证）：
#   CF_API_TOKEN   Cloudflare API Token，权限需要包含该 Zone 的 "Zone Settings: Edit" 与 "Page Rules: Edit"
#   CF_ZONE_ID     auigetvapeshop.com 对应的 Cloudflare Zone ID
#
# 用法：
#   # 预览将要执行的操作，不实际调用写接口
#   ./apply-cloudflare-optimizations.sh
#
#   # 确认无误后真正执行
#   ./apply-cloudflare-optimizations.sh --apply
#
set -euo pipefail

APPLY=false
if [[ "${1:-}" == "--apply" ]]; then
  APPLY=true
fi

if [[ -z "${CF_API_TOKEN:-}" || -z "${CF_ZONE_ID:-}" ]]; then
  echo "错误：缺少 CF_API_TOKEN 或 CF_ZONE_ID 环境变量。" >&2
  echo "请先在 Cursor Dashboard -> Cloud Agents -> Secrets 里添加，再重新运行。" >&2
  exit 1
fi

API_BASE="https://api.cloudflare.com/client/v4/zones/${CF_ZONE_ID}"
AUTH_HEADER="Authorization: Bearer ${CF_API_TOKEN}"

call() {
  local method="$1" path="$2" data="${3:-}"
  if [[ -n "$data" ]]; then
    curl -sS -X "$method" "${API_BASE}${path}" \
      -H "$AUTH_HEADER" -H "Content-Type: application/json" \
      --data "$data"
  else
    curl -sS -X "$method" "${API_BASE}${path}" -H "$AUTH_HEADER"
  fi
}

echo "=== 1. Brotli 压缩 ==="
current_brotli=$(call GET /settings/brotli | python3 -c "import sys,json; print(json.load(sys.stdin)['result']['value'])" 2>/dev/null || echo "unknown")
echo "当前状态: ${current_brotli}"
if [[ "$APPLY" == true ]]; then
  call PATCH /settings/brotli '{"value":"on"}' | python3 -m json.tool
else
  echo "(预览模式，未执行) 将设置为: on"
fi

echo
echo "=== 2. Auto Minify (CSS/JS/HTML) ==="
if [[ "$APPLY" == true ]]; then
  call PATCH /settings/minify '{"value":{"css":"on","html":"on","js":"on"}}' | python3 -m json.tool
else
  echo "(预览模式，未执行) 将设置为: css=on, html=on, js=on"
fi

echo
echo "=== 3. 静态资源长缓存 Page Rule ==="
RULE_TARGET="*auigetvapeshop.com/wp-content/*"
RULE_PAYLOAD=$(cat <<JSON
{
  "targets": [
    {
      "target": "url",
      "constraint": { "operator": "matches", "value": "${RULE_TARGET}" }
    }
  ],
  "actions": [
    { "id": "cache_level", "value": "cache_everything" },
    { "id": "edge_cache_ttl", "value": 2678400 },
    { "id": "browser_cache_ttl", "value": 31536000 }
  ],
  "status": "active",
  "priority": 1
}
JSON
)
echo "计划新增 Page Rule，匹配: ${RULE_TARGET}"
if [[ "$APPLY" == true ]]; then
  call POST /pagerules "$RULE_PAYLOAD" | python3 -m json.tool
else
  echo "(预览模式，未执行)"
  echo "$RULE_PAYLOAD"
fi

echo
if [[ "$APPLY" == true ]]; then
  echo "完成。建议在 Cloudflare 面板 -> Rules -> Page Rules 里人工确认规则已生效，并检查是否与现有规则冲突。"
else
  echo "以上为预览。确认无误后执行： ./apply-cloudflare-optimizations.sh --apply"
fi

echo
echo "注意：以下项目 Cloudflare API 无法自动化，仍需人工在面板操作（原因见 wordpress-fixes/DEPLOY.md）："
echo "  - Polish（图片自动转 WebP/AVIF，需要 Pro 及以上套餐，在 Speed -> Optimization 里手动开启）"
echo "  - Bot Fight Mode / WAF 自定义规则的精细调整（涉及业务判断，不适合无人值守自动执行）"
