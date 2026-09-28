import express from 'express';
import helmet from 'helmet';
import {config} from './config.js';
import projectEnquiries from './routes/project-enquiries.js';

export const createApp=()=>{
  const app=express();
  if(config.trustProxy)app.set('trust proxy',1);
  app.disable('x-powered-by');

  app.use(helmet({
    contentSecurityPolicy:{
      directives:{
        defaultSrc:["'self'"],
        scriptSrc:["'self'","'unsafe-inline'"],
        styleSrc:["'self'","'unsafe-inline'",'https://fonts.googleapis.com'],
        fontSrc:["'self'",'https://fonts.gstatic.com'],
        imgSrc:["'self'",'data:'],
        connectSrc:["'self'"],
        objectSrc:["'none'"],
        baseUri:["'self'"],
        formAction:["'self'"],
        // Safari upgrades LAN HTTP assets to HTTPS when this directive is present.
        // Keep it for the real HTTPS deployment, but disable it during local testing.
        upgradeInsecureRequests:config.environment==='production'?[]:null
      }
    },
    crossOriginEmbedderPolicy:false
  }));

  app.get('/api/health',(request,response)=>response.json({ok:true,service:'distillogic-website'}));
  app.use('/api/project-enquiries',projectEnquiries);
  // Only public pages participate; API, CRM and asset URLs are unchanged.
  app.use((request,response,next)=>{
    if(!['GET','HEAD'].includes(request.method))return next();
    const match=request.path.match(/^\/(index|services|delivery-models|engineering|industries|company|privacy|terms|cookies|contact)(\.html|\/)$/);
    if(!match)return next();
    const query=request.originalUrl.includes('?')?request.originalUrl.slice(request.originalUrl.indexOf('?')):'';
    response.redirect(301,(match[1]==='index'?'/':'/'+match[1])+query);
  });
  app.use(express.static(config.siteRoot,{extensions:['html'],index:'index.html'}));

  app.use((error,request,response,next)=>{
    if(response.headersSent)return next(error);
    console.error(`[backend] ${error.name}: ${error.message}`);
    response.status(500).json({ok:false,error:'The request could not be processed. Please try again later.'});
  });

  return app;
};
