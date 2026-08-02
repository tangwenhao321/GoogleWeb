#!/usr/bin/env bash
#
# auigetvapeshop.com — 通过 SSH/SFTP 一键部署本目录下的修复文件到线上站点。
#
# 本脚本会：
#   1. 把 mu-plugins/au-iget-security-hardening.php 上传到 wp-content/mu-plugins/
#      （目录不存在会自动创建；mu-plugin 上传后立即生效，无需在后台激活）
#   2. 把 wp-cli/fix-content-typos.php 上传到站点根目录下的 wordpress-fixes/wp-cli/
#      （方便直接在服务器上用 wp eval-file 执行）
#   3. 【可选】如果检测到远端有 wp-cli 且传入了 --run-cli-fix 参数，
#      会自动先以 dry-run 方式跑一次内容修复扫描并把结果打印出来（不会自动 --apply，
#      修复内容需要人工复核后手动执行，见输出里的提示）
#
# 前置条件（必须先在 Cursor Dashboard -> Cloud Agents -> Secrets 里配置，
# 再作为环境变量传入；本脚本不会以任何形式记录或回显凭证明文）：
#   SSH_HOST        主机名或 IP，例如 ssh.auigetvapeshop.com 或 SiteGround 提供的 IP
#   SSH_PORT        SSH 端口（SiteGround 常见为 18765，具体见 Site Tools -> Devs -> SSH Keys Manager）
#   SSH_USER        SSH 用户名
#   SSH_KEY_PATH    私钥文件路径（推荐用密钥而非密码；如只有密码，请改用 sshpass，见下方注释）
#   REMOTE_WP_ROOT  站点在服务器上的绝对路径，例如 /home/customer/www/auigetvapeshop.com/public_html
#
# 用法：
#   ./deploy-via-ssh.sh                 # 只上传文件
#   ./deploy-via-ssh.sh --run-cli-fix   # 上传后额外跑一次内容修复的 dry-run 扫描
#
set -euo pipefail

REQUIRED_VARS=(SSH_HOST SSH_PORT SSH_USER SSH_KEY_PATH REMOTE_WP_ROOT)
for v in "${REQUIRED_VARS[@]}"; do
  if [[ -z "${!v:-}" ]]; then
    echo "错误：缺少环境变量 ${v}。" >&2
    echo "请先在 Cursor Dashboard -> Cloud Agents -> Secrets 里添加所有必需变量，再重新运行本脚本。" >&2
    echo "需要的变量列表: ${REQUIRED_VARS[*]}" >&2
    exit 1
  fi
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSH_OPTS=(-i "$SSH_KEY_PATH" -p "$SSH_PORT" -o StrictHostKeyChecking=accept-new)

echo "=== 1. 上传 mu-plugin ==="
ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" "mkdir -p '${REMOTE_WP_ROOT}/wp-content/mu-plugins'"
scp "${SSH_OPTS[@]}" "${SCRIPT_DIR}/mu-plugins/au-iget-security-hardening.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_WP_ROOT}/wp-content/mu-plugins/au-iget-security-hardening.php"
echo "已上传到 ${REMOTE_WP_ROOT}/wp-content/mu-plugins/au-iget-security-hardening.php（立即生效）"

echo
echo "=== 2. 上传内容修复脚本 ==="
ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" "mkdir -p '${REMOTE_WP_ROOT}/wordpress-fixes/wp-cli'"
scp "${SSH_OPTS[@]}" "${SCRIPT_DIR}/wp-cli/fix-content-typos.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_WP_ROOT}/wordpress-fixes/wp-cli/fix-content-typos.php"
echo "已上传到 ${REMOTE_WP_ROOT}/wordpress-fixes/wp-cli/fix-content-typos.php"

if [[ "${1:-}" == "--run-cli-fix" ]]; then
  echo
  echo "=== 3. 远程执行内容修复扫描（dry-run，不会修改数据） ==="
  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" \
    "cd '${REMOTE_WP_ROOT}' && command -v wp >/dev/null 2>&1 && wp eval-file wordpress-fixes/wp-cli/fix-content-typos.php || echo '远端未检测到 wp-cli，请联系主机商开启，或改用 SiteGround Site Tools 里自带的 WP-CLI 终端手动执行。'"
fi

echo
echo "全部完成。建议接下来："
echo "  1. 打开 https://auigetvapeshop.com/ 快速人工检查首页/购物车/结账是否正常（安全头/XML-RPC改动理论上零风险，但仍建议肉眼确认）。"
echo "  2. 复核 dry-run 输出后，SSH 登录执行： wp eval-file wordpress-fixes/wp-cli/fix-content-typos.php --apply"
echo "  3. Woodmart 主题设置、SiteGround Bot Protection 范围收窄、Cloudflare Polish 等仍需人工在面板操作，见 wordpress-fixes/DEPLOY.md。"

# 如果贵司只有密码而没有 SSH 私钥，可以改用 sshpass（需要先安装）：
#   SSHPASS="$SSH_PASSWORD" sshpass -e ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" ...
# 出于安全考虑，不建议长期使用密码方式，SiteGround Site Tools 可以免费生成/托管 SSH Key。
