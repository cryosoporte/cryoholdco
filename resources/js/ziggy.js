const Ziggy = {"url":"http:\/\/localhost:8000","port":8000,"defaults":{},"routes":{"login":{"uri":"login","methods":["GET","HEAD"]},"login.store":{"uri":"login","methods":["POST"]},"logout":{"uri":"logout","methods":["POST"]},"home":{"uri":"\/","methods":["GET","HEAD"]},"storage.local":{"uri":"storage\/{path}","methods":["GET","HEAD"],"wheres":{"path":".*"},"parameters":["path"]},"storage.local.upload":{"uri":"storage\/{path}","methods":["PUT"],"wheres":{"path":".*"},"parameters":["path"]}}};
if (typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined') {
  Object.assign(Ziggy.routes, window.Ziggy.routes);
}
export { Ziggy };
