#!/usr/bin/python

import threading
import time 
import random
import httplib
import signal
import os

RASPERRYS_TO_SPAWN = 270
CLIENTS_TO_SPAWN   = RASPERRYS_TO_SPAWN

GATEWAY_IP="192.168.1.2"
TIMEOUT=90
RUN = True

def sig_handler_term(signum, frame):
  print "SIGTERM received"
  RUN = False
signal.signal(signal.SIGTERM, sig_handler_term)
signal.signal(signal.SIGINT,  sig_handler_term)

class logging():
  def log(self, msg):
    print "Thread " + str(self.id) + ": " + msg

class simRaspberry(threading.Thread, logging):
  def __init__(self, id, gateways = []):
    threading.Thread.__init__(self)
    self.id = id
    self.gateways  = gateways
    self.id        = hex(random.randint(0, 2**32)).replace("0x","").upper() + "-" + hex(random.randint(0, 2**32)).replace("0x","").upper()
    self.cpuserial = hex(random.randint(0, 2**48)).replace("0x","")
    self.doCmd     = 0
    self.state     = "IDLE"
    self.timewait  = 30
    self.latency   = 0;
    signal.signal(signal.SIGTERM, sig_handler_term)
    signal.signal(signal.SIGINT, sig_handler_term)
    
  def run(self):
    self.log("Created RPI id=" + self.id + " cpuserial=" + self.cpuserial)
    self.state = "RPI_BOOTING"
    time.sleep(random.randint(15,45))
    while (RUN):
      for gw in self.gateways:
        startt = time.time()
        try:
          #self.log("Connecting to " + str(gw))
          self.state = "RPI_CONNECTING"
          httpcon = httplib.HTTPConnection(gw[0], gw[1], timeout=TIMEOUT)
          try:
            httpcon.request("GET", "/registerdev.php?deviceId=" + str(self.id))
            res = httpcon.getresponse()
            data = res.read()
            httpcon.close()
            stopt = time.time()
            self.latency = stopt - startt
            self.state = "RPI_IDLE"
          except:
            #self.log("Register timeout")
            self.state = "RPI_REGISTER_TIMEOUT"
        except:
          #self.log("Connection timeout")
          self.state = "RPI_CONNECT_TIMEOUT"
      time.sleep(self.timewait)

class simClient(threading.Thread, logging):
  def __init__(self, id, gateways = []):
    threading.Thread.__init__(self)
    self.id = id
    self.gateways  = gateways
    self.state     = "CLIENT_IDLE"
    self.timewait  = 10
    self.latency   = 0;
    signal.signal(signal.SIGINT, sig_handler_term)
    signal.signal(signal.SIGTERM, sig_handler_term)
    
  def run(self):
    self.log("Created Client")
    self.state = "CLIENT_BOOTING"
    time.sleep(random.randint(60,90))
    while (RUN):
      for gw in self.gateways:
        try:
          startt = time.time()
          self.state = "CLIENT_CONNECTING"
          httpcon = httplib.HTTPConnection(gw[0], gw[1], timeout=TIMEOUT)
          try:
            httpcon.request("GET", "/index.php")
            res = httpcon.getresponse()
            data = res.read()
            httpcon.close()
            stopt = time.time()
            self.latency = stopt - startt
            self.state = "CLIENT_IDLE"
          except:
            #self.log("Register timeout")
            self.state = "CLIENT_READ_TIMEOUT"
        except:
          #self.log("Connection timeout")
          self.state = "CLIENT_CONNECT_TIMEOUT"
      time.sleep(self.timewait)


random.seed(int(time.time()))
for i in range(0,RASPERRYS_TO_SPAWN):
  t = simRaspberry("RPi_"+str(i), [(GATEWAY_IP, "8080")])
  t.start()
for i in range(0,CLIENTS_TO_SPAWN):
  t = simClient("Client_"+str(i), [(GATEWAY_IP, "8080")])
  t.start()
  
states = {}
while(threading.activeCount() > 1):
  latency = 0
  idle_sims = 0
  for s in states:
    states[s] = 0
  for t in threading.enumerate():
    if (isinstance(t, simRaspberry) or isinstance(t, simClient)):
      if not t.state in states:
        states[t.state] = 1
      else:
        states[t.state] += 1
      if "IDLE" in t.state:
        latency += t.latency
        idle_sims += 1
        
  os.system('clear')
  print "Active: " + str(threading.activeCount()) + " (Clients: " + str(CLIENTS_TO_SPAWN) + " RPis: " + str(RASPERRYS_TO_SPAWN) + "); Run = " + str(RUN)
  for s in states:
    spaces = " "*(30 - len(str(s)))
    print s + spaces + str(states[s])
  if idle_sims == 0:
    print "Average latency unavailable"
  else:
    print "Average latency: " + str(float(latency)/idle_sims)
  time.sleep(1)
