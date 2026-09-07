import {createApp} from './app.js';
import {config,validateProductionConfig} from './config.js';

validateProductionConfig();
const app=createApp();

const server=app.listen(config.port,()=>{
  console.log(`DISTILLOGIC website and API available at http://localhost:${config.port}`);
  console.log(`Enquiry destination: ${config.enquiryDestination}`);
});

const shutdown=(signal)=>{
  console.log(`${signal} received. Closing the server.`);
  server.close(()=>process.exit(0));
};

process.on('SIGINT',()=>shutdown('SIGINT'));
process.on('SIGTERM',()=>shutdown('SIGTERM'));

