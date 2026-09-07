import crypto from 'node:crypto';
import {Router} from 'express';
import rateLimit from 'express-rate-limit';
import multer from 'multer';
import {config} from '../config.js';
import {deliverEnquiry} from '../services/enquiry-destination.js';
import {sendSubmissionFailureEmail} from '../services/submission-failure-email.js';

const router=Router();

const allowedExtensions=new Set(['.pdf','.doc','.docx','.xls','.xlsx']);
const allowedMimeTypes=new Set([
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);

class UploadTypeError extends Error{}

const upload=multer({
  storage:multer.memoryStorage(),
  limits:{files:config.maxFiles,fileSize:config.maxFileSizeBytes,fields:25},
  fileFilter:(request,file,callback)=>{
    const extension=file.originalname.toLowerCase().match(/\.[^.]+$/)?.[0]||'';
    if(!allowedExtensions.has(extension)||!allowedMimeTypes.has(file.mimetype)){
      return callback(new UploadTypeError('Only PDF, DOC, DOCX, XLS and XLSX files are allowed.'));
    }
    callback(null,true);
  }
});

const uploadDocuments=(request,response,next)=>{
  upload.array('documents',config.maxFiles)(request,response,(error)=>{
    if(!error)return next();
    if(error instanceof multer.MulterError){
      return response.status(400).json({ok:false,error:'The uploaded files do not meet the limits.'});
    }
    if(error instanceof UploadTypeError){
      return response.status(400).json({ok:false,error:error.message});
    }
    next(error);
  });
};

const enquiryLimiter=rateLimit({
  windowMs:15*60*1000,
  limit:5,
  standardHeaders:'draft-8',
  legacyHeaders:false,
  message:{ok:false,error:'Too many submissions. Please wait before trying again.'}
});

const optionSets={
  service:new Set(['full-project-delivery','work-package-delivery','dedicated-engineering-team','staff-augmentation','application-development','systems-integration','application-modernisation','cloud-devops','data-ai','quality-engineering','application-management','other']),
  projectStage:new Set(['idea-planning','requirements-defined','technical-design','development-started','existing-application','project-recovery','procurement-rfp','immediate-delivery']),
  engagement:new Set(['complete-project','specific-work-package','application-module','dedicated-team','individual-specialists','ongoing-support','not-defined']),
  timeline:new Set(['immediate','within-one-month','one-three-months','three-six-months','six-plus-months','discuss'])
};

const text=(body,key,{required=false,max=500}={})=>{
  const value=typeof body[key]==='string'?body[key].trim():'';
  if(required&&!value)throw new Error(`${key} is required.`);
  if(value.length>max)throw new Error(`${key} is too long.`);
  return value;
};

const option=(body,key)=>{
  const value=text(body,key,{required:true,max:80});
  if(!optionSets[key].has(value))throw new Error(`${key} is not valid.`);
  return value;
};

const parseEnquiry=(body)=>{
  if(text(body,'website',{max:200}))return null;

  const email=text(body,'email',{required:true,max:254}).toLowerCase();
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))throw new Error('email is not valid.');
  if(body.privacyConsent!=='yes')throw new Error('privacyConsent is required.');

  return {
    name:text(body,'name',{required:true,max:120}),
    company:text(body,'company',{required:true,max:160}),
    email,
    phone:text(body,'phone',{max:30}),
    country:text(body,'country',{required:true,max:100}),
    service:option(body,'service'),
    projectTitle:text(body,'projectTitle',{required:true,max:180}),
    requirement:text(body,'requirement',{required:true,max:6000}),
    technologies:text(body,'technologies',{max:1000}),
    projectStage:option(body,'projectStage'),
    engagement:option(body,'engagement'),
    timeline:option(body,'timeline'),
    requestNda:body.requestNda==='Yes',
    language:['en','el'].includes(body.language)?body.language:'en',
    sourcePage:text(body,'sourcePage',{max:300})
  };
};

router.post('/',enquiryLimiter,uploadDocuments,async(request,response,next)=>{
  try{
    const data=parseEnquiry(request.body);
    if(!data)return response.status(202).json({ok:true});

    const files=request.files||[];
    const totalBytes=files.reduce((sum,file)=>sum+file.size,0);
    if(totalBytes>config.maxTotalUploadBytes){
      return response.status(413).json({ok:false,error:'The combined upload is too large.'});
    }

    const reference=`DL-${crypto.randomUUID().split('-')[0].toUpperCase()}`;
    let delivery;
    try{
      delivery=await deliverEnquiry({reference,data,files});
    }catch(error){
      const failureEmailSent=await sendSubmissionFailureEmail({reference,data,files});
      return response.status(502).json({
        ok:false,
        reference,
        failureEmailSent,
        error:data.language==='el'
          ?'Η αίτηση δεν καταχωρίστηκε. Δοκιμάστε ξανά αργότερα.'
          :'The application was not submitted. Please try again later.'
      });
    }

    response.status(202).json({
      ok:true,
      reference,
      stored:delivery.stored!==false,
      mode:delivery.destination,
      confirmationEmailSent:delivery.confirmationEmailSent===true
    });
  }catch(error){
    if(/required|not valid|too long/.test(error.message))return response.status(400).json({ok:false,error:error.message});
    next(error);
  }
});

export default router;
