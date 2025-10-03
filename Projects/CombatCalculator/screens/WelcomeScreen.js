import React from 'react';
import { View, Text, StyleSheet, Image, Pressable } from 'react-native';

export default function WelcomeScreen({ navigation }) {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>💀 Harvester of Souls 💀 </Text>

      <Image 
        source={require('../assets/logo.png')} // image logo
        style={styles.image}
      />

      <Pressable 
        style={styles.button} 
        onPress={() => navigation.navigate('Main')}
      >
        <Text style={styles.buttonText}>Start Game</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#323443',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  title: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#93deff',
    marginBottom: 30,
    textAlign: 'center',
  },
  image: {
    width: 200,
    height: 200,
    marginBottom: 40,
    resizeMode: 'contain',
  },
  button: {
    backgroundColor: 'black',
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 12,
  },
  buttonText: {
    color: '#93deff',
    fontSize: 18,
    fontWeight: 'bold',
  },
});
