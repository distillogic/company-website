import test from 'node:test';
import assert from 'node:assert/strict';
import request from 'supertest';
import {createApp} from '../src/app.js';

const validEnquiry={
  name:'Test User',company:'Example Ltd',email:'test@example.com',country:'Greece',
  service:'application-development',projectTitle:'Customer portal',
  requirement:'We need a secure enterprise customer portal.',
  projectStage:'requirements-defined',engagement:'complete-project',
  timeline:'one-three-months',requestNda:'No',privacyConsent:'yes',language:'en'
};

test('health endpoint responds',async()=>{
  const response=await request(createApp()).get('/api/health');
  assert.equal(response.status,200);
  assert.equal(response.body.ok,true);
});

test('valid enquiry is accepted in mock mode without storage',async()=>{
  const response=await request(createApp()).post('/api/project-enquiries').field(validEnquiry);
  assert.equal(response.status,202);
  assert.equal(response.body.ok,true);
  assert.equal(response.body.stored,false);
  assert.match(response.body.reference,/^DL-[A-F0-9]{8}$/);
});

test('invalid enquiry is rejected',async()=>{
  const response=await request(createApp()).post('/api/project-enquiries').field({...validEnquiry,email:'invalid'});
  assert.equal(response.status,400);
  assert.equal(response.body.ok,false);
});

test('privacy consent is enforced by the server',async()=>{
  const {privacyConsent,...withoutConsent}=validEnquiry;
  const response=await request(createApp()).post('/api/project-enquiries').field(withoutConsent);
  assert.equal(response.status,400);
  assert.match(response.body.error,/privacyConsent/);
});

test('unsupported attachment type is rejected',async()=>{
  const response=await request(createApp())
    .post('/api/project-enquiries')
    .field(validEnquiry)
    .attach('documents',Buffer.from('not an allowed document'),'unsafe.exe');
  assert.equal(response.status,400);
  assert.match(response.body.error,/Only PDF/);
});
