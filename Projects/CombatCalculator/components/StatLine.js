import React from 'react';
import { View, Text, Pressable, StyleSheet, Image } from 'react-native';

export default function StatLine({ label, val, keyName, onPlus, onMinus }) {
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}: {val}</Text>
      <Pressable style={styles.btn} onPress={() => onPlus(keyName)} android_ripple={{ color: '#555' }}>
        <Text> + </Text>
      </Pressable>
      <Pressable style={styles.btn} onPress={() => onMinus(keyName)} android_ripple={{ color: '#555' }}>
        <Text> - </Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
  },
  label: {
    color: '#fff',
    fontSize: 24,
    marginRight: 8,
  },
  btn: {
    backgroundColor: '#7bdff2',
    padding: 6,
    marginLeft: 6,
    borderRadius: 8,
  },
  icon: {
    width: 20,
    height: 20,
    tintColor: '#fff',
  },
});
