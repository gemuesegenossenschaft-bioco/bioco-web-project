const http = require('http')
const { parse } = require('url')
const next = require('next')

const app = next({ dev: false })
const handle = app.getRequestHandler()

app.prepare().then(() => {
  const server = http.createServer((req, res) => {
    handle(req, res, parse(req.url, true))
  })
  const port = process.env.PORT || 3000
  server.listen(port, () => {
    console.log(`> bioco.ch ready on port ${port}`)
  })
})

process.on('SIGINT', () => process.exit(0))
process.on('SIGTERM', () => process.exit(0))
