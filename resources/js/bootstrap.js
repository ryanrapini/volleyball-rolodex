// Breeze's Vue preset deletes this file but leaves `import './bootstrap'` in
// app.js. This project talks to the server exclusively through Inertia, so
// there is nothing to bootstrap here. Kept so the import resolves without
// pulling in axios.
export {};
